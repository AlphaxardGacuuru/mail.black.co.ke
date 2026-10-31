<?php

namespace App\Http\Services\Jobs;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Fetches pages from other people's websites politely and safely:
 * only public http(s) hosts (no internal network, even through redirects
 * or DNS tricks), only what robots.txt allows, with a timeout and a size
 * cap.
 */
class SafePageFetcher
{
    private const MAX_REDIRECTS = 3;

    public function fetch(string $url): string
    {
        $this->assertAllowedByRobots($url);

        return $this->get($url);
    }

    /**
     * Throws when the URL isn't a public http(s) address; returns the IP the
     * host resolved to, which the request is then pinned to.
     */
    public function assertPublicUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user'])) {
            throw new RuntimeException('Only public http(s) links are supported.');
        }

        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443], true)) {
            throw new RuntimeException('Only the standard web ports are supported.');
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : $this->resolve($host);

        if ($ips === []) {
            throw new RuntimeException("Couldn't find the website {$host}.");
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new RuntimeException('That address points to a private network.');
            }
        }

        return $ips[0];
    }

    /**
     * @return array<int, string>
     */
    protected function resolve(string $host): array
    {
        return gethostbynamel($host) ?: [];
    }

    /**
     * Where a link ends up after redirects, without reading the page. Search
     * results come back as Google redirect links; this finds the real site.
     */
    public function finalUrl(string $url): string
    {
        for ($redirects = 0; $redirects <= self::MAX_REDIRECTS; $redirects++) {
            $response = $this->request($url);

            if (! $response->redirect()) {
                return $url;
            }

            $url = $this->absoluteUrl($url, (string) $response->header('Location'));
        }

        throw new RuntimeException('Too many redirects.');
    }

    /**
     * One request, without following redirects, connected to the address
     * that was checked, so DNS can't change between the check and the
     * request.
     */
    protected function request(string $url): Response
    {
        $ip = $this->assertPublicUrl($url);
        $parts = parse_url($url);
        $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));

        return Http::withUserAgent(config('job_search.user_agent'))
            ->timeout(15)
            ->connectTimeout(5)
            ->withOptions([
                'allow_redirects' => false,
                'stream' => true,
                'curl' => [CURLOPT_RESOLVE => ["{$parts['host']}:{$port}:{$ip}"]],
            ])
            ->get($url);
    }

    protected function get(string $url, int $redirects = 0): string
    {
        $response = $this->request($url);

        if ($response->redirect()) {
            if ($redirects >= self::MAX_REDIRECTS) {
                throw new RuntimeException('Too many redirects.');
            }

            $location = $this->absoluteUrl($url, (string) $response->header('Location'));

            return $this->get($location, $redirects + 1);
        }

        if (! $response->successful()) {
            throw new RuntimeException("The page returned HTTP {$response->status()}.");
        }

        $body = $response->toPsrResponse()->getBody();

        if ($body->isSeekable()) {
            $body->rewind();
        }

        $limit = (int) config('job_search.max_page_bytes');
        $content = '';

        while (! $body->eof() && strlen($content) <= $limit) {
            $content .= $body->read(65536);
        }

        if (strlen($content) > $limit) {
            throw new RuntimeException('The page is too large.');
        }

        return $content;
    }

    /**
     * Throws when the site's robots.txt disallows the URL for our agent.
     */
    public function assertAllowedByRobots(string $url): void
    {
        $parts = parse_url($url);
        $origin = strtolower(($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? ''));
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        $rules = Cache::remember(
            'job-search:robots:'.sha1($origin),
            now()->addDay(),
            fn () => $this->robotsRules($origin),
        );

        if (! $this->pathAllowed($path, $rules)) {
            throw new RuntimeException('The website asks bots not to read that page.');
        }
    }

    /**
     * The Allow/Disallow rules for "*" (or our agent) from the site's
     * robots.txt; a missing robots.txt allows everything.
     *
     * @return array<int, array{allow: bool, path: string}>
     */
    protected function robotsRules(string $origin): array
    {
        try {
            $content = $this->get($origin.'/robots.txt');
        } catch (RuntimeException) {
            return [];
        }

        $agent = strtolower(Str::before(config('job_search.user_agent'), '/'));
        $groups = [];
        $current = null;
        $lastWasAgent = false;

        foreach (preg_split('/\R/', $content) as $line) {
            $line = trim(Str::before($line, '#'));

            if (! str_contains($line, ':')) {
                continue;
            }

            [$field, $value] = array_map('trim', explode(':', $line, 2));
            $field = strtolower($field);

            if ($field === 'user-agent') {
                if (! $lastWasAgent) {
                    $current = count($groups);
                    $groups[$current] = ['agents' => [], 'rules' => []];
                }

                $groups[$current]['agents'][] = strtolower($value);
                $lastWasAgent = true;

                continue;
            }

            $lastWasAgent = false;

            if ($current !== null && in_array($field, ['allow', 'disallow'], true) && $value !== '') {
                $groups[$current]['rules'][] = ['allow' => $field === 'allow', 'path' => $value];
            }
        }

        $specific = collect($groups)->first(fn ($group) => in_array($agent, $group['agents'], true));
        $wildcard = collect($groups)->first(fn ($group) => in_array('*', $group['agents'], true));

        return ($specific ?? $wildcard)['rules'] ?? [];
    }

    /**
     * The longest matching rule wins; Allow wins a tie.
     *
     * @param  array<int, array{allow: bool, path: string}>  $rules
     */
    public function pathAllowed(string $path, array $rules): bool
    {
        $best = null;

        foreach ($rules as $rule) {
            $pattern = '#^'.str_replace(['\*', '\$'], ['.*', '$'], preg_quote($rule['path'], '#')).'#';

            if (! preg_match($pattern, $path)) {
                continue;
            }

            $length = strlen($rule['path']);

            if ($best === null || $length > $best['length'] || ($length === $best['length'] && $rule['allow'])) {
                $best = ['length' => $length, 'allow' => $rule['allow']];
            }
        }

        return $best === null || $best['allow'];
    }

    protected function absoluteUrl(string $base, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($base);
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return str_starts_with($location, '/')
            ? $origin.$location
            : $origin.rtrim(dirname($parts['path'] ?? '/'), '/').'/'.$location;
    }
}
