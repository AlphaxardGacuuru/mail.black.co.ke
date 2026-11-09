<?php

namespace App\Services\Stories;

use App\Agents\CareerNewsAgent;
use App\Models\AiUsage;
use App\Models\JobSeekerProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\AiUsageService;
use App\Services\Jobs\SafePageFetcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Posts the daily news story for a career field: a card for each article
 * that matters to the people tagged with it, linking to the article. Only
 * links that resolve to a real, public page are kept. Black Mail's
 * official account posts it, and only people in that field see it. The
 * search uses the roles and skills common to the field, never anything
 * that points to one person.
 */
class CareerNewsStoryPoster
{
    public function __construct(
        protected StoryCardRenderer $cards,
        protected SafePageFetcher $fetcher,
        protected AiUsageService $aiUsageService,
    ) {}

    public function run(string $field): ?Story
    {
        $account = User::query()
            ->where('email', config('job_search.news.account_email'))
            ->first();

        if (! $account) {
            Log::warning('Career news skipped: the official account is missing', ['email' => config('job_search.news.account_email')]);

            return null;
        }

        $label = config("job_search.news.fields.{$field}");
        $cvs = JobSeekerProfile::query()
            ->where('auto_apply', true)
            ->whereJsonContains('career_fields', $field)
            ->pluck('profile');

        if (! $label || $cvs->isEmpty()) {
            return null;
        }

        $articles = $this->articles($label, $cvs);

        if ($articles->isEmpty()) {
            return null;
        }

        return Story::create([
            'user_id' => $account->id,
            'career_field' => $field,
            'text' => $label,
            'media' => $articles
                ->map(fn (array $article) => [
                    'image' => $this->cards->headline('News for you', $article['title'], $article['why']),
                    'url' => $article['url'],
                    'source' => $article['source'],
                    'title' => $article['title'],
                ])
                ->all(),
        ]);
    }

    /**
     * This week's articles for the field, each with a link that loads.
     *
     * @param  Collection<int, array<string, mixed>|null>  $cvs
     * @return Collection<int, array{title: string, source: string, url: string, why: string}>
     */
    protected function articles(string $label, Collection $cvs): Collection
    {
        try {
            $response = CareerNewsAgent::make()
                ->prompt(
                    collect([
                        'Field: '.$label,
                        'Roles: '.$this->mostCommon($cvs, 'target_titles', 8),
                        'Skills: '.$this->mostCommon($cvs, 'skills', 10),
                        'Location: Kenya',
                    ])->implode("\n"),
                    model: config('job_search.news.model'),
                );
        } catch (RateLimitedException $exception) {
            // PostCareerNewsStoryJob tries again later.
            throw $exception;
        } catch (Throwable $exception) {
            Log::warning('Career news search failed', ['field' => $label, 'error' => $exception->getMessage()]);

            return collect();
        }

        $this->aiUsageService->record(null, AiUsage::FEATURE_CAREER_NEWS, $response, searches: 1);

        return Str::of((string) $response->text)
            ->explode("\n")
            ->map(fn (string $line) => Str::of($line)
                ->replace("\u{2014}", ', ')
                ->explode('|')
                ->map(fn (string $part) => Str::squish(trim($part, " *_-\t")))
                ->all())
            ->filter(fn (array $parts) => count($parts) >= 4 && Str::startsWith($parts[2], ['http://', 'https://']))
            ->map(fn (array $parts) => [
                'title' => Str::limit($parts[0], 140),
                'source' => Str::limit($parts[1], 60),
                'url' => $this->realUrl(rtrim($parts[2], '.,;)')),
                'why' => Str::limit($parts[3], 160),
            ])
            ->filter(fn (array $article) => $article['url'] !== null && $article['title'] !== '')
            ->unique('url')
            ->take((int) config('job_search.news.max_articles'))
            ->values();
    }

    /**
     * The values of a CV list most of the field's people share, so the
     * search is about the field rather than anyone in it.
     *
     * @param  Collection<int, array<string, mixed>|null>  $cvs
     */
    protected function mostCommon(Collection $cvs, string $key, int $limit): string
    {
        return $cvs
            ->flatMap(fn (?array $cv) => collect($cv[$key] ?? [])
                ->map(fn (string $value) => Str::of($value)->squish()->title()->value())
                ->unique())
            ->countBy()
            ->sortDesc()
            ->keys()
            ->take($limit)
            ->implode(', ');
    }

    /**
     * The article's own address after redirects (search results come back
     * as Google redirect links), or null when the page doesn't exist or the
     * site doesn't answer. The page itself isn't read: many news sites turn
     * bots away, and the link is for people.
     */
    protected function realUrl(string $url): ?string
    {
        try {
            $url = $this->fetcher->existingUrl($url);
        } catch (Throwable) {
            return null;
        }

        return str_ends_with((string) parse_url($url, PHP_URL_HOST), 'vertexaisearch.cloud.google.com') ? null : $url;
    }
}
