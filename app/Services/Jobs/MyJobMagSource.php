<?php

namespace App\Services\Jobs;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * myjobmag.co.ke's job pages. The board is listed through its RSS feed like
 * any other (see JobBoardSource), but its pages carry no structured data,
 * so the description, location and "Method of Application" (where an apply
 * email usually appears) are read from its layout. Some pages ("Vacancies
 * at Acme") list several jobs, each read on its own (see roles()).
 */
class MyJobMagSource
{
    /**
     * @return array{description: ?string, location: ?string, apply_email: ?string, apply_instructions: ?string, apply_url: ?string}
     */
    public function parseJobPage(string $html): array
    {
        $xpath = $this->xpath($html);

        $text = fn (string $query) => $this->text($xpath->query($query)?->item(0)?->textContent);

        $location = $text('//ul[contains(@class, "job-key-info")]/li[span[contains(@class, "jkey-title")][normalize-space(.)="Location"]]/span[contains(@class, "jkey-info")]');

        $instructions = $text('//*[@id="application-method"]/following-sibling::div[1]');

        // "Go to Acme on acme.bamboohr.com to apply" links to MyJobMag's
        // /apply-now/{id}, which redirects to the employer's form.
        $applyLink = $xpath->query('//*[@id="application-method"]/following-sibling::div[1]//a[contains(@href, "/apply-now/")]/@href')?->item(0)?->nodeValue;

        return [
            'description' => Str::limit($text('//div[contains(@class, "job-details")]'), 20000, '') ?: null,
            'location' => Str::limit($location, 250, '') ?: null,
            'apply_email' => self::firstEmail($instructions),
            'apply_instructions' => Str::limit($instructions, 2000, '') ?: null,
            'apply_url' => $this->absoluteUrl($applyLink),
        ];
    }

    /**
     * The jobs on a page that lists several, each under its own heading
     * with its own link, key info, details and apply link. Empty for a page
     * with a single job, which parseJobPage() reads whole.
     *
     * @return list<array{title: string, url: string, location: ?string, description: ?string, apply_url: ?string}>
     */
    public function roles(string $html): array
    {
        $xpath = $this->xpath($html);

        $roles = collect(iterator_to_array($xpath->query('//h2[starts-with(@id, "job")][a[contains(@class, "subjob-title")]]')))
            ->map(function (DOMElement $heading) use ($xpath) {
                $text = fn (string $query) => $this->text($xpath->query($query, $heading)?->item(0)?->textContent);
                $link = $xpath
                    ->query('a[contains(@class, "subjob-title")]', $heading)
                    ->item(0);
                $id = Str::after($heading->getAttribute('id'), 'job');

                // Each job's apply link carries the id in its heading's.
                $applyLink = ctype_digit($id)
                    ? $xpath->query('//*[@id="application-method"]/following-sibling::div[1]//a[contains(concat(@href, "#"), "/apply-now/'.$id.'#")]/@href')?->item(0)?->nodeValue
                    : null;

                return [
                    'title' => Str::limit($this->text($link->textContent), 250, ''),
                    'url' => $this->absoluteUrl($link->getAttribute('href')),
                    'location' => Str::limit($text('following-sibling::ul[contains(@class, "job-key-info")][1]/li[span[contains(@class, "jkey-title")][normalize-space(.)="Location"]]/span[contains(@class, "jkey-info")]'), 250, '') ?: null,
                    'description' => Str::limit($text('following-sibling::div[contains(@class, "job-details")][1]'), 20000, '') ?: null,
                    'apply_url' => $this->absoluteUrl($applyLink),
                ];
            })
            ->filter(fn (array $role) => $role['title'] !== '' && $role['url'] !== null)
            ->values();

        return $roles->count() > 1
            ? $roles->all()
            : [];
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
    public static function splitTitle(string $title): array
    {
        $position = strrpos($title, ' at ');

        return $position === false
            ? [$title, null]
            : [trim(substr($title, 0, $position)), trim(substr($title, $position + 4)) ?: null];
    }

    protected function xpath(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NONET | LIBXML_NOERROR);

        return new DOMXPath($document);
    }

    /**
     * A link on the site made absolute: "/job/x" → "https://www.myjobmag.co.ke/job/x".
     */
    protected function absoluteUrl(?string $link): ?string
    {
        return $link
            ? Str::of($link)
                ->unless(Str::startsWith($link, 'http'), fn ($url) => $url
                    ->start('/')
                    ->prepend('https://www.myjobmag.co.ke'))
                ->toString()
            : null;
    }

    protected function text(?string $value): string
    {
        $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace("/[ \t\x{00A0}]+/u", ' ', $value);

        return trim(preg_replace("/\s*\n\s*(\n\s*)+/", "\n\n", $value));
    }
}
