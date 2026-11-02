<?php

namespace App\Services\Jobs;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the schema.org JobPosting data job boards add to their pages for
 * Google's job search, so any board that publishes it can be read without
 * code of its own. Nodes may sit in an "@graph" and point at each other by
 * "@id" (the employer, the place, its address); those links are followed.
 */
class StructuredJobData
{
    /**
     * Nodes on the page by "@id".
     *
     * @var array<string, array<string, mixed>>
     */
    protected array $nodes = [];

    /**
     * @var Collection<int, array<string, mixed>>
     */
    protected Collection $allNodes;

    /**
     * The job on the page, or null when the page has no JobPosting data.
     * $boardHost keeps the board's own addresses out of the apply email.
     *
     * @return array{title: string, company: ?string, location: ?string, industry: ?string, description: ?string, apply_email: ?string, apply_instructions: ?string, posted_at: ?Carbon, valid_through: ?Carbon}|null
     */
    public function read(string $html, string $boardHost): ?array
    {
        preg_match_all('#<script[^>]+application/ld\+json[^>]*>(.*?)</script>#is', $html, $matches);

        $this->nodes = [];

        $nodes = $this->allNodes = collect($matches[1])
            ->map(fn (string $json) => json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5, 'UTF-8'), true))
            ->filter(fn ($data) => is_array($data))
            ->flatMap(fn (array $data) => $this->flatten($data))
            ->each(function (array $node) {
                if (is_string($node['@id'] ?? null)) {
                    $this->nodes[$node['@id']] = $node;
                }
            });

        $job = $nodes->first(fn (array $node) => in_array('JobPosting', (array) ($node['@type'] ?? []), true));

        if (! $job || trim((string) ($job['title'] ?? '')) === '') {
            return null;
        }

        $description = self::text($job['description'] ?? null);
        $instructions = preg_match('/\b(how to apply|method of application|application procedure)\b[:\s]*(.+)$/is', $description, $applyMatch)
            ? trim($applyMatch[2])
            : null;

        return [
            'title' => Str::limit(self::text($job['title']), 250, ''),
            'company' => Str::limit(self::text($this->name($job['hiringOrganization'] ?? null)), 250, '') ?: null,
            'location' => $this->location($job, $boardHost),
            'industry' => Str::limit(self::text(is_string($job['industry'] ?? null) ? $job['industry'] : null), 250, '') ?: null,
            'description' => Str::limit($description, 20000, '') ?: null,
            'apply_email' => $this->applyEmail($instructions ?? $description, $boardHost),
            'apply_instructions' => $instructions ? Str::limit($instructions, 2000, '') : null,
            'posted_at' => $this->date($job['datePosted'] ?? null),
            'valid_through' => $this->date($job['validThrough'] ?? null),
        ];
    }

    /**
     * HTML to readable plain text, keeping paragraph and line breaks.
     */
    public static function text(mixed $html): string
    {
        return Str::of(is_string($html) ? $html : '')
            ->replaceMatches('#<br\s*/?>|</(p|div|li|h[1-6]|tr)>#i', "\n")
            ->stripTags()
            ->pipe(fn ($text) => html_entity_decode((string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))
            ->replaceMatches("/[ \t\x{00A0}]+/u", ' ')
            ->replaceMatches("/ *\n */", "\n")
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();
    }

    /**
     * Every node in the data: one object, a list, or an "@graph".
     *
     * @return array<int, array<string, mixed>>
     */
    protected function flatten(array $data): array
    {
        if (array_is_list($data)) {
            return collect($data)
                ->filter(fn ($item) => is_array($item))
                ->flatMap(fn (array $item) => $this->flatten($item))
                ->all();
        }

        return isset($data['@graph']) && is_array($data['@graph'])
            ? $this->flatten($data['@graph'])
            : [$data];
    }

    /**
     * A node with what its "@id" points at merged underneath.
     *
     * @return array<string, mixed>
     */
    protected function resolve(mixed $value): array
    {
        if (! is_array($value) || array_is_list($value)) {
            return [];
        }

        return [...($this->nodes[$value['@id'] ?? ''] ?? []), ...$value];
    }

    protected function name(mixed $value): ?string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value) && array_is_list($value)) {
            return $this->name($value[0] ?? null);
        }

        $name = $this->resolve($value)['name'] ?? null;

        return is_string($name) ? $name : null;
    }

    protected function location(array $job, string $boardHost): ?string
    {
        // Boards sometimes fill a listing's address from their own office's
        // (BrighterMonday puts "Westlands" on every job), so the board's own
        // place names don't count.
        $boardPlaces = $this->allNodes
            ->filter(fn (array $node) => is_string($node['url'] ?? null)
                && Str::after((string) parse_url($node['url'], PHP_URL_HOST), 'www.') === Str::after($boardHost, 'www.'))
            ->flatMap(fn (array $node) => $this->places($node['address'] ?? null))
            ->map(fn (string $place) => mb_strtolower(trim($place)))
            ->reject(fn (string $place) => in_array($place, ['kenya', 'ke'], true));

        $remote = collect((array) ($job['jobLocationType'] ?? []))
            ->contains(fn ($type) => is_string($type) && strtoupper($type) === 'TELECOMMUTE');

        $location = collect([$remote ? 'Remote' : null])
            ->merge($this->places($job['jobLocation'] ?? null))
            ->filter(fn ($place) => is_string($place) && trim($place) !== '')
            ->map(fn (string $place) => trim($place))
            ->reject(fn (string $place) => $boardPlaces->contains(mb_strtolower($place)))
            ->unique(fn (string $place) => mb_strtolower($place))
            ->sortBy(fn (string $place) => in_array(mb_strtolower($place), ['kenya', 'ke'], true))
            ->take(5)
            ->implode(', ');

        return Str::limit($location, 250, '') ?: null;
    }

    /**
     * Town, street and region names under a jobLocation, following links.
     *
     * @return Collection<int, string>
     */
    protected function places(mixed $value, int $depth = 0): Collection
    {
        if ($depth > 4 || $value === null) {
            return collect();
        }

        if (is_string($value)) {
            return collect([$value]);
        }

        if (is_array($value) && array_is_list($value)) {
            return collect($value)
                ->flatMap(fn ($item) => $this->places($item, $depth + 1));
        }

        $inline = is_array($value) ? $value : [];
        $linked = $this->nodes[$inline['@id'] ?? ''] ?? [];

        return collect([$inline, $linked])
            ->flatMap(fn (array $node) => collect(['addressLocality', 'streetAddress', 'addressRegion'])
                ->map(fn (string $field) => $node[$field] ?? null)
                ->filter(fn ($place) => is_string($place) && mb_strlen($place) <= 60)
                ->merge($this->places($node['address'] ?? null, $depth + 1)));
    }

    /**
     * The first address in the text that isn't the board's own (its support
     * desk, or a placeholder).
     */
    protected function applyEmail(string $text, string $boardHost): ?string
    {
        $boardDomain = Str::after(mb_strtolower($boardHost), 'www.');

        preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9-]+(?:\.[A-Z0-9-]+)*\.[A-Z]{2,}/i', $text, $matches);

        return collect($matches[0])
            ->map(fn (string $email) => mb_strtolower($email))
            ->first(function (string $email) use ($boardDomain) {
                $domain = Str::after($email, '@');

                return $domain !== $boardDomain
                    && ! str_ends_with($domain, '.'.$boardDomain)
                    && ! in_array($domain, ['example.com', 'anonymous.com', 'email.com', 'domain.com'], true);
            });
    }

    protected function date(mixed $value): ?Carbon
    {
        try {
            return is_string($value) && $value !== '' ? Carbon::parse($value) : null;
        } catch (Throwable) {
            return null;
        }
    }
}
