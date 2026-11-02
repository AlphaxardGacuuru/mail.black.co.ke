<?php

namespace App\Services\Jobs;

use App\Agents\CareerPageAgent;
use App\Models\AiUsage;
use App\Models\CompanyCareerPage;
use App\Models\JobPosting;
use App\Services\AiUsageService;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * A company's own careers page, added by the user. Pages vary too much to
 * parse with rules, so the AI lists the positions, but only when the page
 * has changed since the last visit.
 */
class CompanyCareerPageSource
{
    public function __construct(protected SafePageFetcher $fetcher, protected AiUsageService $aiUsageService) {}

    /**
     * Returns how many new postings were found.
     */
    public function sync(CompanyCareerPage $page): int
    {
        $text = $this->pageText($this->fetcher->fetch($page->url), $page->url);

        // A page found by search that turns out not to be a careers page is
        // set aside (and never re-added) before any AI is spent on it.
        if ($page->source === CompanyCareerPage::SOURCE_SEARCH && ! self::looksLikeCareersPage($page->url, $text)) {
            $page->update([
                'last_fetched_at' => now(),
                'last_error' => 'Not a careers page.',
                'dismissed_at' => now(),
            ]);

            return 0;
        }

        $hash = hash('sha256', $text);

        if ($hash === $page->content_hash) {
            $page->update(['last_fetched_at' => now(), 'last_error' => null]);

            return 0;
        }

        $response = CareerPageAgent::make()
            ->prompt(
                "Careers page: {$page->url}\n\n".Str::limit($text, (int) config('job_search.max_page_chars_for_ai'), ''),
                model: config('job_search.model'),
            );

        $this->aiUsageService->record($page->user_id, AiUsage::FEATURE_CAREER_PAGE_READ, $response, $page);

        $company = Str::limit(trim((string) ($response['company'] ?? '')), 250, '') ?: $page->company_name;
        $created = 0;

        foreach (array_slice((array) ($response['jobs'] ?? []), 0, 30) as $job) {
            $title = Str::limit(trim((string) ($job['title'] ?? '')), 250, '');

            if ($title === '') {
                continue;
            }

            $posting = JobPosting::firstOrCreate(
                ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_COMPANY_PAGE, $page->id, $title, (string) ($job['location'] ?? ''))],
                [
                    'source' => JobPosting::SOURCE_COMPANY_PAGE,
                    'user_id' => $page->user_id,
                    'company_career_page_id' => $page->id,
                    'title' => $title,
                    'company' => $company,
                    'location' => Str::limit(trim((string) ($job['location'] ?? '')), 250, '') ?: null,
                    'description' => trim((string) ($job['description'] ?? '')) ?: null,
                    'url' => $this->verifiedUrl($job['url'] ?? null, $text) ?? $page->url,
                    'apply_email' => $this->verifiedEmail($job['apply_email'] ?? null, $text),
                    'apply_instructions' => Str::limit(trim((string) ($job['apply_instructions'] ?? '')), 2000, '') ?: null,
                    'posted_at' => now(),
                    'details_fetched_at' => now(),
                ],
            );

            $created += $posting->wasRecentlyCreated ? 1 : 0;
        }

        $page->update([
            'company_name' => $company,
            'content_hash' => $hash,
            'last_fetched_at' => now(),
            'last_error' => null,
        ]);

        return $created;
    }

    public static function looksLikeCareersUrl(string $url): bool
    {
        return (bool) preg_match('#career|job|vacanc|recruit|opening|hiring|join-?us|work-?with-?us|opportunit#i', (string) parse_url($url, PHP_URL_PATH).' '.parse_url($url, PHP_URL_HOST));
    }

    /**
     * Enough text, and either a careers-like address or careers words in
     * the text.
     */
    public static function looksLikeCareersPage(string $url, string $text): bool
    {
        if (mb_strlen($text) < 200) {
            return false;
        }

        $mentions = preg_match_all('/\b(careers?|vacanc(y|ies)|job openings?|open positions?|apply (now|here|for)|recruitment|we.re hiring|join our team)\b/i', $text);

        return $mentions >= 2 || (self::looksLikeCareersUrl($url) && $mentions >= 1);
    }

    /**
     * The page's readable text, with each link's address after its text so
     * apply links and mailto addresses survive.
     */
    public function pageText(string $html, string $baseUrl): string
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.self::revealEmails($html), LIBXML_NONET | LIBXML_NOERROR);
        $xpath = new DOMXPath($document);

        foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//svg|//template')) as $node) {
            $node->parentNode?->removeChild($node);
        }

        foreach (iterator_to_array($xpath->query('//a[@href]')) as $link) {
            $href = trim($link->getAttribute('href'));

            if ($href !== '' && ! str_starts_with($href, '#') && ! str_starts_with(strtolower($href), 'javascript:')) {
                $link->appendChild($document->createTextNode(' ('.$this->absolute($href, $baseUrl).')'));
            }
        }

        foreach (iterator_to_array($xpath->query('//p|//div|//li|//br|//h1|//h2|//h3|//h4|//tr')) as $block) {
            $block->appendChild($document->createTextNode("\n"));
        }

        $text = html_entity_decode((string) ($xpath->query('//body')->item(0)?->textContent ?? $document->textContent), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t\x{00A0}]+/u", ' ', $text);

        return trim(preg_replace("/\s*\n\s*/", "\n", $text));
    }

    /**
     * Addresses hidden by Cloudflare's email protection ("[email protected]"),
     * written out again. Many job sites use it; readers see the address, so
     * the email check against the page needs to as well. The address is
     * hex, XOR'd with the first byte.
     */
    public static function revealEmails(string $html): string
    {
        $decode = fn (string $hex): string => collect(str_split(substr($hex, 2), 2))
            ->map(fn (string $pair) => chr(hexdec($pair) ^ hexdec(substr($hex, 0, 2))))
            ->implode('');

        return Str::of($html)
            ->replaceMatches('#<(a|span)\b[^>]*data-cfemail="([0-9a-f]+)"[^>]*>.*?</\1>#is', fn (array $match) => e($decode($match[2])))
            ->replaceMatches('#/cdn-cgi/l/email-protection\#([0-9a-f]+)#i', fn (array $match) => 'mailto:'.$decode($match[1]))
            ->toString();
    }

    /**
     * Only an address that's actually on the page; the AI must not supply
     * one of its own.
     */
    protected function verifiedEmail(mixed $email, string $text): ?string
    {
        $email = MyJobMagSource::firstEmail(Str::after((string) $email, 'mailto:'));

        return $email && str_contains(mb_strtolower($text), $email) ? $email : null;
    }

    protected function verifiedUrl(mixed $url, string $text): ?string
    {
        $url = trim((string) $url);

        return preg_match('#^https?://#i', $url) && str_contains($text, $url) ? Str::limit($url, 2000, '') : null;
    }

    protected function absolute(string $href, string $baseUrl): string
    {
        if (preg_match('#^(https?:|mailto:)#i', $href)) {
            return $href;
        }

        $parts = parse_url($baseUrl);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return str_starts_with($href, '/') ? $origin.$href : $origin.'/'.ltrim($href, './');
    }
}
