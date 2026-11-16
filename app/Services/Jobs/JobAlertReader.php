<?php

namespace App\Services\Jobs;

use App\Agents\JobAlertAgent;
use App\Models\AiUsage;
use App\Models\JobBoard;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\MailMessage;
use App\Services\AiUsageService;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Turns job alert emails the user signed up for on other sites into
 * postings for them. A job on a board we read anyway (BrighterMonday,
 * MyJobMag) joins that board and is read like its other jobs; any other
 * job (LinkedIn, Fuzu, ...) is the user's own, known by its title, company
 * and link, and can only be applied to on that site.
 */
class JobAlertReader
{
    public function __construct(protected AiUsageService $aiUsageService) {}

    /**
     * The site a job alert came from, or null when the message isn't one:
     * mail from a job site that links to several job pages. Job sites also
     * send welcomes, sign-in codes and notifications, which link to none,
     * and alert subjects often don't mention jobs ("Safaricom, Jambojet
     * +160 More").
     */
    public static function alertSource(MailMessage $message): ?string
    {
        $domain = Str::of((string) ($message->from_address['address'] ?? ''))
            ->lower()
            ->after('@')
            ->toString();

        $site = collect(config('job_search.alerts.senders'))
            ->first(fn (string $name, string $sender) => $domain === $sender || str_ends_with($domain, '.'.$sender));

        return $site && self::jobLinks($message)->count() >= (int) config('job_search.alerts.min_job_links')
            ? $site
            : null;
    }

    /**
     * The distinct job pages the message links to, with click tracking
     * taken off.
     *
     * @return Collection<int, string>
     */
    public static function jobLinks(MailMessage $message): Collection
    {
        preg_match_all('#href\s*=\s*["\']\s*(https?://[^"\'\s]+)#i', (string) $message->body_html, $matches);

        return collect($matches[1])
            ->map(fn (string $href) => Str::before(self::unwrap(html_entity_decode($href)), '?'))
            ->filter(fn (string $url) => collect(config('job_search.alerts.job_links'))
                ->contains(fn (string $pattern) => preg_match($pattern, $url) === 1))
            ->unique()
            ->values();
    }

    /**
     * The address a click-tracking link leads to: alerts wrap every link,
     * with the real one inside it, percent-encoded
     * ("https://tracking.myjobmag.co.ke/CL0/https:%2F%2Fwww.myjobmag.co.ke%2Fjob%2F...").
     * Other links come back as they are.
     */
    public static function unwrap(string $url): string
    {
        return preg_match('#.(https?(?::|%3A)(?:%2F){2}[^/&\s]+)#i', $url, $match)
            ? rawurldecode($match[1])
            : $url;
    }

    /**
     * The site name for a link from an alert (for "View on LinkedIn").
     */
    public static function siteName(string $url): ?string
    {
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));

        return collect(config('job_search.alerts.senders'))
            ->first(fn (string $name, string $sender) => $host === $sender || str_ends_with($host, '.'.$sender));
    }

    /**
     * Returns how many new postings the alert added, or null when today's
     * allowance of alerts was already used.
     */
    public function read(JobSeekerProfile $profile, MailMessage $message): ?int
    {
        $source = self::alertSource($message);

        if (! $source) {
            return 0;
        }

        $key = "job-search:alerts:{$profile->user_id}:".now()->toDateString();

        Cache::add($key, 0, now()->endOfDay());

        if (Cache::increment($key) > (int) config('job_search.alerts.max_per_user_per_day')) {
            return null;
        }

        [$text, $links] = $this->textWithNumberedLinks((string) ($message->body_html ?: nl2br(e((string) $message->body_text))));

        $response = JobAlertAgent::make()
            ->prompt(
                "Job alert from {$source}\nSubject: {$message->subject}\n\n".Str::limit($text, (int) config('job_search.alerts.max_chars_for_ai'), ''),
                model: config('job_search.model'),
            );

        $this->aiUsageService->record($profile->user_id, AiUsage::FEATURE_JOB_ALERT_READ, $response, $message);

        $boards = JobBoard::query()
            ->enabled()
            ->get();

        $created = collect((array) ($response['jobs'] ?? []))
            ->take((int) config('job_search.alerts.max_jobs_per_alert'))
            ->map(fn ($job) => [
                'title' => Str::limit(trim((string) ($job['title'] ?? '')), 250, ''),
                'company' => Str::limit(trim((string) ($job['company'] ?? '')), 250, '') ?: null,
                'location' => Str::limit(trim((string) ($job['location'] ?? '')), 250, '') ?: null,
                // Only a link that's in the email.
                'url' => $links->get((int) ($job['link'] ?? 0)),
            ])
            ->filter(fn (array $job) => $job['title'] !== '' && $job['url'])
            ->sum(fn (array $job) => $this->store($profile, $job, $boards, $source, $message) ? 1 : 0);

        $profile->update(['last_alert_at' => now(), 'last_alert_source' => $source]);

        return $created;
    }

    /**
     * @param  array{title: string, company: ?string, location: ?string, url: string}  $job
     * @param  Collection<int, JobBoard>  $boards
     */
    protected function store(JobSeekerProfile $profile, array $job, Collection $boards, string $source, MailMessage $message): bool
    {
        $boardUrl = Str::before($job['url'], '?');
        $board = $boards->first(fn (JobBoard $board) => parse_url($boardUrl, PHP_URL_HOST) === $board->host && $board->isJobLink($boardUrl));

        // A job on a board we read: the board's page gives the details and
        // often an email to apply to. The alert names the job, as some
        // boards' pages don't.
        if ($board) {
            return JobPosting::firstOrCreate(
                ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_BOARD, $boardUrl)],
                [
                    'source' => JobPosting::SOURCE_BOARD,
                    'job_board_id' => $board->id,
                    'title' => $job['title'],
                    'company' => $job['company'],
                    'location' => $job['location'],
                    'url' => $boardUrl,
                    'posted_at' => now(),
                ],
            )->wasRecentlyCreated;
        }

        // Alert links carry tracking that changes with every email, so the
        // job itself is what's matched.
        return JobPosting::firstOrCreate(
            ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_ALERT, $profile->user_id, $job['title'], (string) $job['company'], (string) $job['location'])],
            [
                'source' => JobPosting::SOURCE_ALERT,
                'user_id' => $profile->user_id,
                'title' => $job['title'],
                'company' => $job['company'],
                'location' => $job['location'],
                'description' => "From a {$source} job alert. Only the title, employer and location are known.",
                'url' => Str::limit($job['url'], 2000, ''),
                'posted_at' => $message->received_at ?? now(),
                'details_fetched_at' => now(),
            ],
        )->wasRecentlyCreated;
    }

    /**
     * The email's readable text with each http(s) link replaced by a number
     * ("Accountant [3]"), and the links by number. Numbers are shorter than
     * tracking links and the AI can't alter them.
     *
     * @return array{0: string, 1: Collection<int, string>}
     */
    protected function textWithNumberedLinks(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR);
        $xpath = new DOMXPath($document);
        $links = collect();

        foreach (iterator_to_array($xpath->query('//script|//style|//head')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('//a[@href]')) as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if (preg_match('#^https?://#i', $href)) {
                $links->put($links->count() + 1, self::unwrap($href));
                $anchor->appendChild($document->createTextNode(' ['.$links->count().']'));
            }
        }

        foreach (iterator_to_array($xpath->query('//p|//div|//li|//br|//tr|//h1|//h2|//h3|//td')) as $block) {
            $block->appendChild($document->createTextNode("\n"));
        }

        $text = Str::of(html_entity_decode((string) $document->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches("/[ \t\x{00A0}\x{200C}\x{034F}]+/u", ' ')
            ->replaceMatches("/\s*\n\s*/", "\n")
            ->trim()
            ->toString();

        return [$text, $links];
    }
}
