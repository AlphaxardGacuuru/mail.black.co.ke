<?php

namespace App\Services\Jobs;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * myjobmag.co.ke's job pages. The board is listed through its RSS feed like
 * any other (see JobBoardSource), but its pages carry no structured data,
 * so the description, location and "Method of Application" (where an apply
 * email usually appears) are read from its layout.
 */
class MyJobMagSource
{
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
    public static function splitTitle(string $title): array
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
}
