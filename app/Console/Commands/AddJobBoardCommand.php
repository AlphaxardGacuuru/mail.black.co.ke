<?php

namespace App\Console\Commands;

use App\Models\JobBoard;
use App\Services\Jobs\JobBoardSource;
use App\Services\Jobs\SafePageFetcher;
use App\Services\Jobs\StructuredJobData;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adds a job board after checking it can be read: where its jobs are
 * listed (a sitemap or feed from robots.txt or the home page), which links
 * are job pages, that robots.txt allows them, and that the pages carry
 * structured job data (or, with --reader=ai, that they can be fetched for
 * the AI to read). It stays off until switched on, after a person has
 * checked the board's terms allow it.
 */
class AddJobBoardCommand extends Command
{
    protected $signature = 'job-boards:add
        {url : The board\'s home page}
        {--name= : Display name (defaults to the host)}
        {--listing= : Feed, sitemap or page listing the jobs (found automatically if left out)}
        {--pattern= : Regular expression job page links match (worked out from the listing if left out)}
        {--reader=structured : How job pages are read: structured (schema.org data) or ai (for boards without it)}
        {--enable : Switch the board on now (only once its terms are checked)}';

    protected $description = 'Check a job board can be read and add it';

    public function handle(SafePageFetcher $fetcher, JobBoardSource $source, StructuredJobData $structured): int
    {
        $url = rtrim((string) $this->argument('url'), '/');
        $reader = (string) $this->option('reader');

        if (! in_array($reader, [JobBoard::READER_STRUCTURED, JobBoard::READER_AI], true)) {
            return $this->failWith('--reader must be structured or ai.');
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        $origin = 'https://'.$host;

        try {
            $fetcher->assertPublicUrl($origin);
        } catch (Throwable $exception) {
            return $this->failWith($exception->getMessage());
        }

        $listing = $this->option('listing') ?: $this->findListing($fetcher, $origin);

        if (! $listing) {
            return $this->failWith("Couldn't find a jobs sitemap or feed. Pass one with --listing.");
        }

        $this->line("Listing: {$listing}");

        $board = new JobBoard([
            'name' => $this->option('name') ?: $host,
            'host' => $host,
            'listing_url' => $listing,
            'link_pattern' => '#^#',
            'reader' => $reader,
        ]);

        try {
            $entries = $this->entries($source, $board, $listing);
        } catch (Throwable $exception) {
            return $this->failWith("Couldn't read the listing: {$exception->getMessage()}");
        }

        $links = $entries
            ->pluck('url')
            ->unique()
            ->values();

        // A feed only lists posts, so on a jobs blog every link in it is a
        // job; a sitemap or page also links everything else.
        $isFeed = array_key_exists('title', (array) $entries->first());

        $board->link_pattern = $this->option('pattern') ?: ($isFeed
            ? '#^https://'.preg_quote($host, '#').'/[^?]+$#'
            : $this->patternFor($links, $host));

        if (! $board->link_pattern) {
            return $this->failWith('No job links found in the listing. Pass the job link pattern with --pattern.');
        }

        $this->line("Job links: {$board->link_pattern}");

        $samples = $links
            ->filter(fn (string $link) => $board->isJobLink($link))
            ->take(3);

        // The AI reader is only checked for pages it may fetch; reading the
        // samples with it would cost for nothing.
        $read = $samples
            ->map(function (string $link) use ($fetcher, $structured, $host, $reader) {
                try {
                    $html = $fetcher->fetch($link);

                    return $reader === JobBoard::READER_AI
                        ? ['title' => $link]
                        : $structured->read($html, $host);
                } catch (Throwable $exception) {
                    $this->warn("{$link}: {$exception->getMessage()}");

                    return null;
                }
            })
            ->filter();

        if ($read->isEmpty()) {
            return $this->failWith($reader === JobBoard::READER_AI
                ? "The job pages couldn't be fetched (or robots.txt blocks them)."
                : 'The job pages have no structured job data (or robots.txt blocks them). Try --reader=ai.');
        }

        $this->table(
            ['Title', 'Company', 'Location', 'Apply email'],
            $read
                ->map(fn (array $job) => [$job['title'], $job['company'] ?? '-', $job['location'] ?? '-', $job['apply_email'] ?? '-'])
                ->all(),
        );

        // Re-adding a board updates how it's read without switching it off.
        $board->enabled = $this->option('enable') || JobBoard::query()
            ->where('host', $host)
            ->value('enabled');

        JobBoard::updateOrCreate(['host' => $host], $board->only(['name', 'listing_url', 'link_pattern', 'reader', 'enabled']));

        $this->info($board->enabled
            ? "{$board->name} added and switched on."
            : "{$board->name} added, switched off. Check its terms allow automated reading, then run: php artisan job-boards:toggle {$host}");

        return self::SUCCESS;
    }

    /**
     * A jobs sitemap named in robots.txt, else a feed linked from the home
     * page (a jobs one if there is one: on a jobs blog the main feed is the
     * latest jobs).
     */
    protected function findListing(SafePageFetcher $fetcher, string $origin): ?string
    {
        $jobWords = fn (string $link) => preg_match('/job|listing|vacanc|career/i', (string) parse_url($link, PHP_URL_PATH)) === 1;

        try {
            $sitemap = collect(preg_split('/\R/', $fetcher->fetch($origin.'/robots.txt')))
                ->map(fn (string $line) => preg_match('/^\s*sitemap:\s*(\S+)/i', $line, $match) ? $match[1] : null)
                ->filter(fn (?string $link) => $link && $jobWords($link))
                ->sortByDesc(fn (string $link) => str_contains($link, 'listing'))
                ->first();
        } catch (Throwable) {
            $sitemap = null;
        }

        if ($sitemap) {
            return $sitemap;
        }

        try {
            preg_match_all('#<link[^>]+type=["\']application/(?:rss|atom)\+xml["\'][^>]*>#i', $fetcher->fetch($origin), $tags);
        } catch (Throwable) {
            return null;
        }

        return collect($tags[0])
            ->map(fn (string $tag) => preg_match('#href=["\']([^"\']+)#i', $tag, $match) ? html_entity_decode($match[1]) : null)
            ->filter(fn (?string $link) => $link && str_starts_with($link, 'http') && ! str_contains($link, 'comments'))
            ->sortByDesc($jobWords)
            ->first();
    }

    /**
     * Entries in the listing on the board's own site; for a sitemap index,
     * in its first sitemap.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function entries(JobBoardSource $source, JobBoard $board, string $listing): Collection
    {
        $read = $source->read($board, $listing);

        if ($read['entries']->isEmpty() && $read['sitemaps']) {
            $read = $source->read($board, (string) array_key_first($read['sitemaps']));
        }

        return $read['entries']
            ->filter(fn (array $entry) => parse_url($entry['url'], PHP_URL_HOST) === $board->host)
            ->values();
    }

    /**
     * The first path segment most job links share ("/listings/..."), as a
     * pattern for links one level below it.
     */
    protected function patternFor(Collection $links, string $host): ?string
    {
        $segment = $links
            ->map(fn (string $link) => Str::of((string) parse_url($link, PHP_URL_PATH))
                ->trim('/')
                ->explode('/'))
            ->filter(fn (Collection $parts) => $parts->count() === 2 && $parts->last() !== '')
            ->map(fn (Collection $parts) => $parts->first())
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return $segment
            ? '#^https://'.preg_quote($host, '#').'/'.preg_quote($segment, '#').'/[^?/]+/?$#'
            : null;
    }

    protected function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
