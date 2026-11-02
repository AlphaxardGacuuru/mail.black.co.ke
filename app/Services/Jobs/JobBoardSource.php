<?php

namespace App\Services\Jobs;

use App\Agents\JobPageAgent;
use App\Models\AiUsage;
use App\Models\JobBoard;
use App\Models\JobPosting;
use App\Services\AiUsageService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use SimpleXMLElement;
use Throwable;

/**
 * Reads any job board from the job_boards table: its listing (an RSS/Atom
 * feed, a sitemap or an HTML page) gives the job links, then each job page
 * is read for its schema.org JobPosting data, myjobmag's layout, or (on
 * boards with neither) by the AI, within a daily allowance.
 */
class JobBoardSource
{
    public function __construct(
        protected SafePageFetcher $fetcher,
        protected StructuredJobData $structured,
        protected MyJobMagSource $myJobMag,
        protected CompanyCareerPageSource $careerPages,
        protected AiUsageService $aiUsageService,
    ) {}

    /**
     * Read one listing of the board and store job links not seen before,
     * to be read later. A sitemap index stores nothing itself: it returns
     * the sitemaps in it that changed since they were last read (address
     * => last modified), each to be read in its own queued job.
     *
     * @return array{created: int, sitemaps: array<string, ?string>}
     */
    public function list(JobBoard $board, string $url): array
    {
        $listing = $this->read($board, $url);

        return ['created' => $this->store($board, $listing['entries']), 'sitemaps' => $listing['sitemaps']];
    }

    /**
     * The links in one listing, without storing anything: job entries, or
     * for a sitemap index, its changed sitemaps.
     *
     * @return array{entries: Collection<int, array<string, mixed>>, sitemaps: array<string, ?string>}
     */
    public function read(JobBoard $board, string $url): array
    {
        $content = $this->fetcher->fetch($url);

        if (str_starts_with($content, "\x1f\x8b")) {
            $content = (string) @gzdecode($content, (int) config('job_search.max_page_bytes'));
        }

        $xml = preg_match('/^\s*(<\?xml|<(rss|feed|urlset|sitemapindex)\b)/i', $content)
            ? @simplexml_load_string($content, options: LIBXML_NOCDATA | LIBXML_NONET)
            : false;

        if ($xml instanceof SimpleXMLElement && $xml->getName() === 'sitemapindex') {
            return ['entries' => collect(), 'sitemaps' => $this->changedSitemaps($board, $xml)];
        }

        $entries = match ($xml instanceof SimpleXMLElement ? $xml->getName() : null) {
            'urlset' => $this->sitemapEntries($xml),
            'rss' => $this->rssEntries($board, $xml),
            'feed' => $this->atomEntries($xml),
            default => $this->pageEntries($content, $url),
        };

        return ['entries' => $entries, 'sitemaps' => []];
    }

    /**
     * Remember a sitemap was read at this version, so it's skipped until it
     * changes.
     */
    public function markSitemapRead(string $url, ?string $lastModified): void
    {
        if ($lastModified) {
            Cache::put($this->sitemapKey($url), $lastModified, now()->addDays(30));
        }
    }

    /**
     * Fill in a posting from its job page. Returns false when the page had
     * nothing to read; a posting known only by its link then stays out of
     * matching.
     */
    public function fetchDetails(JobPosting $posting): bool
    {
        $html = $this->fetcher->fetch($posting->url);

        $details = match ($posting->board?->reader) {
            JobBoard::READER_MYJOBMAG => $this->myJobMag->parseJobPage($html),
            JobBoard::READER_AI => $this->aiDetails($posting, $html),
            default => $this->structured->read($html, (string) parse_url($posting->url, PHP_URL_HOST)),
        };

        $posting->update([
            ...collect($details ?? [])
                ->reject(fn ($value) => $value === null || $value === '')
                ->all(),
            'details_fetched_at' => now(),
        ]);

        return $details !== null;
    }

    /**
     * Job pages the AI may still read today, across all boards.
     */
    public function aiPagesLeftToday(): int
    {
        return max(0, (int) config('job_search.boards.max_ai_pages_per_day') - (int) Cache::get($this->aiPagesKey(), 0));
    }

    /**
     * The advert on a page with no structured data, read by the AI. A page
     * that isn't a job advert loses its title, which keeps it out of
     * matching.
     *
     * @return array<string, mixed>|null
     */
    protected function aiDetails(JobPosting $posting, string $html): ?array
    {
        // The page's main content, without the menus and footer around it:
        // <main>, else the longest <article> (blogs also use <article> for
        // the "related posts" boxes), else the whole page.
        preg_match('#<main\b.*?</main>#is', $html, $main);
        preg_match_all('#<article\b.*?</article>#is', $html, $articles);
        $content = $main[0] ?? collect($articles[0])
            ->sortByDesc(fn (string $article) => mb_strlen(strip_tags($article)))
            ->first() ?? $html;
        $text = $this->careerPages->pageText("<html><body>{$content}</body></html>", $posting->url);

        Cache::add($this->aiPagesKey(), 0, now()->endOfDay());
        Cache::increment($this->aiPagesKey());

        $response = JobPageAgent::make()
            ->prompt(
                "Job page: {$posting->url}\n\n".Str::limit($text, (int) config('job_search.boards.max_job_page_chars_for_ai'), ''),
                model: config('job_search.model'),
            );

        $this->aiUsageService->record(null, AiUsage::FEATURE_JOB_PAGE_READ, $response, $posting);

        if (! ($response['is_job'] ?? false)) {
            $posting->update(['title' => '']);

            return null;
        }

        // The AI sometimes names the address only in the instructions.
        $email = MyJobMagSource::firstEmail((string) ($response['apply_email'] ?? ''))
            ?? MyJobMagSource::firstEmail((string) ($response['apply_instructions'] ?? ''));
        $boardDomain = Str::after((string) $posting->board?->host, 'www.');

        return [
            'title' => Str::limit(trim((string) ($response['title'] ?? '')), 250, ''),
            'company' => Str::limit(trim((string) ($response['company'] ?? '')), 250, ''),
            'location' => Str::limit(trim((string) ($response['location'] ?? '')), 250, ''),
            'description' => trim((string) ($response['description'] ?? '')),
            // Only an address that's on the page, and not the board's own.
            'apply_email' => $email && str_contains(mb_strtolower($text), $email) && ! str_ends_with($email, '@'.$boardDomain)
                ? $email
                : null,
            'apply_instructions' => Str::limit(trim((string) ($response['apply_instructions'] ?? '')), 2000, ''),
            'valid_through' => $this->date((string) ($response['closing_date'] ?? ''))?->endOfDay(),
        ];
    }

    /**
     * @param  Collection<int, array{url: string, title?: string, company?: ?string, industry?: ?string, description?: ?string, posted_at?: ?Carbon}>  $entries
     */
    protected function store(JobBoard $board, Collection $entries): int
    {
        $oldest = now()->subDays((int) config('job_search.max_posting_age_days'));

        return $entries
            ->filter(fn (array $entry) => $board->isJobLink($entry['url']))
            ->reject(fn (array $entry) => ($entry['posted_at'] ?? null)?->lt($oldest))
            ->unique('url')
            ->sortByDesc(fn (array $entry) => ($entry['posted_at'] ?? null)?->timestamp ?? 0)
            ->take((int) config('job_search.boards.max_links_per_listing'))
            ->sum(fn (array $entry) => JobPosting::firstOrCreate(
                ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_BOARD, $entry['url'])],
                [
                    'source' => JobPosting::SOURCE_BOARD,
                    'job_board_id' => $board->id,
                    // Sitemaps give only the link: the title comes with the
                    // page, and until then the posting isn't matched.
                    'title' => Str::limit(trim($entry['title'] ?? ''), 250, ''),
                    'company' => Str::limit(trim($entry['company'] ?? ''), 250, '') ?: null,
                    'industry' => Str::limit(trim($entry['industry'] ?? ''), 250, '') ?: null,
                    'description' => trim($entry['description'] ?? '') ?: null,
                    'url' => $entry['url'],
                    'posted_at' => $entry['posted_at'] ?? null,
                ],
            )->wasRecentlyCreated ? 1 : 0);
    }

    /**
     * @return array<string, ?string>
     */
    protected function changedSitemaps(JobBoard $board, SimpleXMLElement $xml): array
    {
        $oldest = now()->subDays((int) config('job_search.max_posting_age_days'));

        return collect(iterator_to_array($xml->sitemap, false))
            ->mapWithKeys(fn (SimpleXMLElement $sitemap) => [trim((string) $sitemap->loc) => trim((string) $sitemap->lastmod) ?: null])
            ->filter(fn (?string $lastModified, string $url) => parse_url($url, PHP_URL_HOST) === $board->host)
            ->reject(fn (?string $lastModified) => $this->date($lastModified)?->lt($oldest))
            ->reject(fn (?string $lastModified, string $url) => $lastModified !== null
                && Cache::get($this->sitemapKey($url)) === $lastModified)
            ->take((int) config('job_search.boards.max_sitemaps_per_run'))
            ->all();
    }

    protected function sitemapEntries(SimpleXMLElement $xml): Collection
    {
        return collect(iterator_to_array($xml->url, false))
            ->map(fn (SimpleXMLElement $entry) => [
                'url' => trim((string) $entry->loc),
                'posted_at' => $this->date((string) $entry->lastmod),
            ]);
    }

    protected function rssEntries(JobBoard $board, SimpleXMLElement $xml): Collection
    {
        return collect(iterator_to_array($xml->channel->item, false))
            ->map(function (SimpleXMLElement $item) use ($board) {
                // myjobmag's titles read "Sales Manager at Acme Ltd".
                [$title, $company] = $board->reader === JobBoard::READER_MYJOBMAG
                    ? MyJobMagSource::splitTitle(trim((string) $item->title))
                    : [trim((string) $item->title), null];

                return [
                    'url' => trim((string) $item->link),
                    'title' => $title,
                    'company' => $company,
                    'industry' => trim((string) $item->industry),
                    'description' => StructuredJobData::text((string) $item->description),
                    'posted_at' => $this->date((string) $item->pubDate),
                ];
            });
    }

    protected function atomEntries(SimpleXMLElement $xml): Collection
    {
        return collect(iterator_to_array($xml->entry, false))
            ->map(fn (SimpleXMLElement $entry) => [
                'url' => trim((string) $entry->link['href']),
                'title' => trim((string) $entry->title),
                'description' => StructuredJobData::text((string) ($entry->summary ?: $entry->content)),
                'posted_at' => $this->date((string) ($entry->published ?: $entry->updated)),
            ]);
    }

    /**
     * Links on an HTML listing page.
     */
    protected function pageEntries(string $html, string $pageUrl): Collection
    {
        preg_match_all('#<a\s[^>]*href\s*=\s*["\']([^"\'\#]+)#i', $html, $matches);

        return collect($matches[1])
            ->map(fn (string $href) => ['url' => $this->absoluteUrl(html_entity_decode(trim($href)), $pageUrl)]);
    }

    protected function absoluteUrl(string $href, string $pageUrl): string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($pageUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return str_starts_with($href, '/')
            ? $origin.$href
            : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$href;
    }

    protected function aiPagesKey(): string
    {
        return 'job-search:ai-job-pages:'.now()->toDateString();
    }

    protected function sitemapKey(string $url): string
    {
        return 'job-search:sitemap:'.sha1($url);
    }

    protected function date(?string $value): ?Carbon
    {
        try {
            return $value ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
