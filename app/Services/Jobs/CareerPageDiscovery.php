<?php

namespace App\Services\Jobs;

use App\Agents\CareerPageSearchAgent;
use App\Jobs\SyncCareerPageJob;
use App\Models\AiUsage;
use App\Models\CompanyCareerPage;
use App\Models\JobBoard;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Services\AiUsageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Responses\Data\UrlCitation;
use Throwable;

/**
 * Finds employers' careers pages for a job seeker with Gemini's Google
 * Search and adds them to the pages checked daily. Each page is confirmed
 * to be a careers page the first time it's read (see
 * CompanyCareerPageSource), so a wrong guess costs one page fetch, not AI.
 */
class CareerPageDiscovery
{
    public function __construct(protected SafePageFetcher $fetcher, protected AiUsageService $aiUsageService) {}

    /**
     * Returns how many pages were added.
     */
    public function discover(JobSeekerProfile $profile): int
    {
        if (! $profile->isReady()) {
            return 0;
        }

        // Recorded first, so a failing search waits for the next interval
        // rather than retrying every run.
        $profile->update(['last_discovery_at' => now()]);

        $user = $profile->user;
        $slots = min(
            (int) config('job_search.discovery.max_new_per_search'),
            (int) config('job_search.discovery.max_pages') - $user->companyCareerPages()
                ->active()
                ->where('source', CompanyCareerPage::SOURCE_SEARCH)
                ->count(),
        );

        if ($slots <= 0) {
            return 0;
        }

        [$roles, $places] = $this->searchTerms($profile);

        // People looking for the same roles in the same places share one
        // search per interval.
        $candidates = Cache::remember(
            'job-search:discovery:'.sha1(json_encode([$roles, $places])),
            now()->addDays((int) config('job_search.discovery.interval_days')),
            fn () => $this->search($roles, $places),
        );

        $excluded = collect($profile->preference('excluded_companies'))
            ->map(fn (string $company) => JobPosting::normalizeCompany($company))
            ->filter();
        $knownHosts = $user->companyCareerPages()->pluck('host')->filter()->all();
        $added = 0;

        foreach ($candidates as $candidate) {
            if ($added >= $slots) {
                break;
            }

            $host = CompanyCareerPage::hostOf($candidate['url']);
            $names = collect([$candidate['company'], Str::before($host, '.')])
                ->map(fn (string $name) => JobPosting::normalizeCompany($name))
                ->filter();

            if (in_array($host, $knownHosts, true) || $names->intersect($excluded)->isNotEmpty()) {
                continue;
            }

            $page = $user->companyCareerPages()->firstOrCreate(
                ['url_hash' => hash('sha256', $candidate['url'])],
                [
                    'url' => $candidate['url'],
                    'source' => CompanyCareerPage::SOURCE_SEARCH,
                    'host' => $host,
                    'company_name' => $candidate['company'] ?: null,
                ],
            );

            $knownHosts[] = $host;

            if ($page->wasRecentlyCreated) {
                SyncCareerPageJob::dispatch($page->id);
                $added++;
            }
        }

        return $added;
    }

    /**
     * Roles from the CV and preferences, and where to look.
     *
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    protected function searchTerms(JobSeekerProfile $profile): array
    {
        $normalize = fn (array $values, int $limit) => collect($values)
            ->map(fn ($value) => Str::of((string) $value)->squish()->title()->toString())
            ->filter()
            ->unique()
            ->sort()
            ->take($limit)
            ->values()
            ->all();

        $roles = $normalize([...$profile->preference('keywords'), ...($profile->profile['target_titles'] ?? [])], 5);
        $places = $normalize($profile->preference('locations') ?: [$profile->profile['location'] ?? 'Kenya'], 3);

        return [$roles, $places ?: ['Kenya']];
    }

    /**
     * @param  array<int, string>  $roles
     * @param  array<int, string>  $places
     * @return array<int, array{company: string, url: string}>
     */
    protected function search(array $roles, array $places): array
    {
        try {
            $response = CareerPageSearchAgent::make()
                ->prompt(
                    'Roles: '.implode(', ', $roles)."\nPlaces: ".implode(', ', $places),
                    model: config('job_search.discovery.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Careers page search failed', ['error' => $exception->getMessage()]);

            return [];
        }

        // The answer is cached and shared by everyone looking for the same
        // roles in the same places, so it's billed to the system, not a user.
        $this->aiUsageService->record(null, AiUsage::FEATURE_CAREER_PAGE_SEARCH, $response);

        $candidates = collect();

        foreach (preg_split('/\R/', (string) $response->text) as $line) {
            if (preg_match('#^\s*[-*\d.)]*\s*(.*?)\s*\|\s*<?(https?://[^\s>|]+)#i', $line, $matches)) {
                $candidates->push(['company' => Str::limit(trim($matches[1], " *_\t"), 200, ''), 'url' => $matches[2]]);
            }
        }

        // The search results the answer was grounded on: real pages, behind
        // Google redirect links.
        $citations = collect($response->meta->citations ?? [])
            ->filter(fn ($citation) => $citation instanceof UrlCitation)
            ->take((int) config('job_search.discovery.max_links_resolved'));

        foreach ($citations as $citation) {
            try {
                $url = $this->fetcher->finalUrl($citation->url);
            } catch (Throwable) {
                continue;
            }

            $candidates->push(['company' => Str::limit((string) $citation->title, 200, ''), 'url' => $url]);
        }

        return $this->usable($candidates);
    }

    /**
     * Public http(s) links on employers' own sites, one per site, careers
     * pages first.
     *
     * @param  Collection<int, array{company: string, url: string}>  $candidates
     * @return array<int, array{company: string, url: string}>
     */
    protected function usable(Collection $candidates): array
    {
        $blocked = JobBoard::query()
            ->pluck('host')
            ->map(fn (string $host) => Str::after($host, 'www.'))
            ->merge(config('job_search.discovery.blocked_hosts'));

        return $candidates
            ->map(fn (array $candidate) => [...$candidate, 'url' => Str::before(rtrim($candidate['url'], '.,;)'), '#')])
            ->filter(function (array $candidate) use ($blocked) {
                $host = CompanyCareerPage::hostOf($candidate['url']);

                return $host !== ''
                    && strlen($candidate['url']) <= 2000
                    && ! str_ends_with($host, 'vertexaisearch.cloud.google.com')
                    && ! $blocked->contains(fn (string $site) => $host === $site || str_ends_with($host, '.'.$site));
            })
            ->sortByDesc(fn (array $candidate) => CompanyCareerPageSource::looksLikeCareersUrl($candidate['url']))
            ->unique(fn (array $candidate) => CompanyCareerPage::hostOf($candidate['url']))
            ->values()
            ->all();
    }
}
