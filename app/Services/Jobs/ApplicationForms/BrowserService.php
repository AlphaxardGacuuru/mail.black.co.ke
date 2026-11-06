<?php

namespace App\Services\Jobs\ApplicationForms;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Runs resources/browser/application-form.mjs, the headless browser that
 * reads and fills in employers' application forms.
 */
class BrowserService
{
    /**
     * The form's questions, found by following the link to it.
     *
     * @return array{url?: string, adapter?: string, captcha?: ?string, blocker?: ?string, fields?: array<int, array<string, mixed>>, error?: string}
     */
    public function inspect(string $url, string $screenshotPath): array
    {
        return $this->run([
            'mode' => 'inspect',
            'url' => $url,
            'screenshots' => ['form' => $screenshotPath],
        ], (int) config('job_search.application_forms.prepare_timeout'));
    }

    /**
     * Fills in the form and submits it.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, string>  $answers  by question key
     * @param  array<string, string>  $files  upload name (e.g. "cv") => local path
     * @param  array{filled: string, confirmation: string}  $screenshots
     * @return array{submitted?: bool, url?: string, captcha?: ?string, error?: string}
     */
    public function submit(string $url, array $fields, array $answers, array $files, array $screenshots): array
    {
        $captcha = config('job_search.application_forms.captcha');

        return $this->run([
            'mode' => 'submit',
            'url' => $url,
            'fields' => $fields,
            'answers' => (object) $answers,
            'files' => (object) $files,
            'screenshots' => $screenshots,
            'captcha' => $captcha['key'] ? $captcha : null,
        ], (int) config('job_search.application_forms.submit_timeout'));
    }

    /**
     * @param  array<string, mixed>  $instructions
     * @return array<string, mixed>
     */
    protected function run(array $instructions, int $timeout): array
    {
        $result = Process::path(base_path())
            ->timeout($timeout + 30)
            ->input(json_encode([...$instructions, 'timeoutSeconds' => $timeout], JSON_THROW_ON_ERROR))
            ->run([config('job_search.application_forms.node_binary'), 'resources/browser/application-form.mjs']);

        $output = json_decode((string) Str::of($result->output())
            ->trim()
            ->explode("\n")
            ->last(), true);

        if (! is_array($output)) {
            throw new RuntimeException('The browser stopped unexpectedly: '.Str::limit(trim($result->errorOutput()), 500));
        }

        return $output;
    }
}
