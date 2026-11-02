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
     * mail from a job site that looks like it's about jobs (LinkedIn also
     * sends messages and notifications).
     */
    public static function alertSource(MailMessage $message): ?string
    {
        $from = mb_strtolower((string) ($message->from_address['address'] ?? ''));
        $domain = Str::after($from, '@');

        $site = collect(config('job_search.alerts.senders'))
            ->first(fn (string $name, string $sender) => $domain === $sender || str_ends_with($domain, '.'.$sender));

        $aboutJobs = preg_match('/job|vacanc|career|hiring|opportunit|position|alert/i', Str::before($from, '@').' '.$message->subject) === 1;

        return $site && $aboutJobs ? $site : null;
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
        // often an email to apply to.
        if ($board) {
            return JobPosting::firstOrCreate(
                ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_BOARD, $boardUrl)],
                [
                    'source' => JobPosting::SOURCE_BOARD,
                    'job_board_id' => $board->id,
                    'title' => '',
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
                $links->put($links->count() + 1, $href);
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
