<?php

namespace App\Http\Services\Jobs;

use App\Models\JobPosting;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;
use Throwable;

/**
 * myjobmag.co.ke, read through its published RSS feed and its public job
 * pages (both allowed by its robots.txt). The feed gives the listing; each
 * job page gives the full description and the "Method of Application",
 * where an apply email usually appears.
 */
class MyJobMagSource
{
    public function __construct(protected SafePageFetcher $fetcher) {}

    /**
     * Store postings from the feed that aren't known yet; returns how many
     * were new.
     */
    public function syncFeed(): int
    {
        $xml = simplexml_load_string(
            $this->fetcher->fetch(config('job_search.myjobmag.feed_url')),
            options: LIBXML_NOCDATA | LIBXML_NONET,
        );

        if ($xml === false) {
            return 0;
        }

        $created = 0;

        foreach ($xml->channel->item ?? [] as $item) {
            $url = trim((string) $item->link);

            if (! preg_match('#^https://www\.myjobmag\.co\.ke/jobs/[^?]+$#', $url)) {
                continue;
            }

            [$title, $company] = $this->splitTitle(trim((string) $item->title));

            $posting = JobPosting::firstOrCreate(
                ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_MYJOBMAG, $url)],
                [
                    'source' => JobPosting::SOURCE_MYJOBMAG,
                    'title' => Str::limit($title, 250, ''),
                    'company' => $company ? Str::limit($company, 250, '') : null,
                    'industry' => Str::limit(trim((string) $item->industry), 250, '') ?: null,
                    'description' => trim(html_entity_decode(strip_tags((string) $item->description))) ?: null,
                    'url' => $url,
                    'posted_at' => $this->date((string) $item->pubDate),
                ],
            );

            $created += $posting->wasRecentlyCreated ? 1 : 0;
        }

        return $created;
    }

    /**
     * Fill in the description, location and apply details from the job page.
     */
    public function fetchDetails(JobPosting $posting): void
    {
        $details = $this->parseJobPage($this->fetcher->fetch($posting->url));

        $posting->update([
            'description' => $details['description'] ?: $posting->description,
            'location' => $details['location'] ?: $posting->location,
            'apply_email' => $details['apply_email'],
            'apply_instructions' => $details['apply_instructions'],
            'details_fetched_at' => now(),
        ]);
    }

    /**
     * @return array{description: ?string, location: ?string, apply_email: ?string, apply_instructions: ?string}
     */
    public function parseJobPage(string $html): array
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR);
        $xpath = new DOMXPath($document);

        $text = fn (string $query) => $this->text($xpath->query($query)?->item(0)?->textContent);

        $location = $text('//ul[contains(@class, "job-key-info")]/li[span[contains(@class, "jkey-title")][normalize-space(.)="Location"]]/span[contains(@class, "jkey-info")]');

        $instructions = $text('//*[@id="application-method"]/following-sibling::div[1]');

        return [
            'description' => Str::limit($text('//div[contains(@class, "job-details")]'), 20000, '') ?: null,
            'location' => Str::limit($location, 250, '') ?: null,
            'apply_email' => self::firstEmail($instructions),
            'apply_instructions' => Str::limit($instructions, 2000, '') ?: null,
        ];
    }

    /**
     * The first email address in the text, lower-cased.
     */
    public static function firstEmail(?string $text): ?string
    {
        return preg_match('/[A-Z0-9._%+-]+@[A-Z0-9-]+(?:\.[A-Z0-9-]+)*\.[A-Z]{2,}/i', (string) $text, $matches)
            ? mb_strtolower($matches[0])
            : null;
    }

    /**
     * "Sales Manager at Acme Ltd" → ["Sales Manager", "Acme Ltd"].
     *
     * @return array{0: string, 1: ?string}
     */
    protected function splitTitle(string $title): array
    {
        $position = strrpos($title, ' at ');

        return $position === false
            ? [$title, null]
            : [trim(substr($title, 0, $position)), trim(substr($title, $position + 4)) ?: null];
    }

    protected function text(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t\x{00A0}]+/u", ' ', $value);

        return trim(preg_replace("/\s*\n\s*(\n\s*)+/", "\n\n", $value));
    }

    protected function date(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
