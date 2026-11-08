<?php

namespace App\Services\Jobs;

use App\Agents\JobSalaryAgent;
use App\Jobs\EstimateJobSalaryJob;
use App\Models\AiUsage;
use App\Models\JobPosting;
use App\Services\AiUsageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * What a job pays: the figure its posting states, or the AI's estimate of
 * the usual range for the role. Worked out the first time anyone opens
 * the job (jobs nobody opens cost nothing), then kept on the posting for
 * everyone matched to it.
 */
class JobSalaryService
{
    private const DESCRIPTION_CHARS = 6000;

    public function __construct(
        protected AiUsageService $aiUsageService,
    ) {}

    /**
     * Whether the AI has yet to look at this job's pay.
     */
    public function pending(JobPosting $posting): bool
    {
        return $posting->salary === null && $posting->salary_checked_at === null;
    }

    /**
     * Price the job in the background. Asked again only once the last
     * request has had time to finish, so reopening the job (or a failed
     * request) doesn't pay for it twice.
     */
    public function request(JobPosting $posting): void
    {
        if ($this->pending($posting) && Cache::add("job-salary:{$posting->id}", true, now()->addMinutes(5))) {
            EstimateJobSalaryJob::dispatch($posting->id);
        }
    }

    public function estimate(JobPosting $posting): void
    {
        try {
            $response = JobSalaryAgent::make()
                ->prompt($this->prompt($posting), model: config('job_search.model'));
        } catch (Throwable $exception) {
            Log::warning('Job salary estimate failed', ['job_posting_id' => $posting->id, 'error' => $exception->getMessage()]);

            return;
        }

        $this->aiUsageService->record(null, AiUsage::FEATURE_JOB_SALARY, $response, $posting);

        [$min, $max] = collect([(int) ($response['min'] ?? 0), (int) ($response['max'] ?? 0)])
            ->sort()
            ->values()
            ->all();

        $posting->update([
            'salary' => $max > 0 ? [
                'min' => $min ?: null,
                'max' => $max,
                'currency' => Str::of((string) ($response['currency'] ?? ''))
                    ->trim()
                    ->upper()
                    ->limit(3, '')
                    ->whenEmpty(fn () => Str::of('KES'))
                    ->toString(),
                'period' => (string) ($response['period'] ?? 'month'),
                'stated' => (bool) ($response['stated'] ?? false),
                'basis' => Str::limit(trim((string) ($response['basis'] ?? '')), 200) ?: null,
            ] : null,
            'salary_checked_at' => now(),
        ]);
    }

    protected function prompt(JobPosting $posting): string
    {
        return collect([
            "Title: {$posting->title}",
            $posting->company ? "Company: {$posting->company}" : null,
            $posting->location ? "Location: {$posting->location}" : null,
            $posting->industry ? "Field: {$posting->industry}" : null,
            'Description: '.Str::limit((string) $posting->description, self::DESCRIPTION_CHARS, ' [...]'),
        ])
            ->filter()
            ->implode("\n");
    }
}
