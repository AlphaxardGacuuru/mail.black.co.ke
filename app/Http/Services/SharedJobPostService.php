<?php

namespace App\Http\Services;

use App\Agents\SharedJobPostAgent;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Models\User;
use App\Services\AiUsageService;
use App\Services\Jobs\JobMatcher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Files\Image;
use Throwable;

/**
 * Job posts the user shares from WhatsApp, Telegram and the like: text, a
 * link, or posters and screenshots. The AI reads the jobs out of the post
 * and each one is added to the user's applications.
 */
class SharedJobPostService
{
    public function __construct(
        protected AiUsageService $aiUsageService,
        protected JobMatcher $matcher,
    ) {
        //
    }

    /**
     * @param  array<int, UploadedFile>  $images
     * @return Collection<int, JobApplication> the applications added
     */
    public function add(User $user, string $text, array $images): Collection
    {
        $profile = $user->jobSeekerProfile;

        if (! $profile?->isReady()) {
            throw ValidationException::withMessages(['post' => ['Turn on job search in Settings first.']]);
        }

        $this->withinDailyAllowance($profile);

        $jobs = $this->read($profile, $text, $images);

        if ($jobs->isEmpty()) {
            throw ValidationException::withMessages(['post' => ['No job found in this post.']]);
        }

        $postings = $jobs
            ->map(fn (array $job) => $this->posting($profile, $job))
            ->reject(fn (JobPosting $posting) => $posting->applications()
                ->where('user_id', $user->id)
                ->exists())
            ->values();

        if ($postings->isEmpty()) {
            throw ValidationException::withMessages(['post' => ['You already added this job.']]);
        }

        return $this->matcher->addShared($profile, $postings);
    }

    protected function withinDailyAllowance(JobSeekerProfile $profile): void
    {
        $key = "job-search:shared:{$profile->user_id}:".now()->toDateString();

        Cache::add($key, 0, now()->endOfDay());

        if (Cache::increment($key) > (int) config('job_search.shared.max_per_user_per_day')) {
            throw ValidationException::withMessages(['post' => ['You\'ve added as many jobs as you can today. Try again tomorrow.']]);
        }
    }

    /**
     * The jobs in the post, with an apply email or link only when it's
     * really there: in the text, or (unverifiable) in an image.
     *
     * @param  array<int, UploadedFile>  $images
     * @return Collection<int, array<string, mixed>>
     */
    protected function read(JobSeekerProfile $profile, string $text, array $images): Collection
    {
        try {
            $response = SharedJobPostAgent::make()
                ->prompt(
                    'Shared job post'.($text !== '' ? ":\n\n{$text}" : ' (images only).'),
                    attachments: collect($images)
                        ->map(fn (UploadedFile $image) => Image::fromUpload($image))
                        ->all(),
                    model: config('job_search.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Reading a shared job post failed', ['user_id' => $profile->user_id, 'error' => $exception->getMessage()]);

            throw ValidationException::withMessages(['post' => ['Couldn\'t read this post. Try again in a moment.']]);
        }

        $this->aiUsageService->record($profile->user_id, AiUsage::FEATURE_SHARED_POST_READ, $response, $profile);

        $inPost = fn (string $value) => $value !== '' && ($images !== [] || Str::contains($text, $value, ignoreCase: true));

        return collect((array) ($response['jobs'] ?? []))
            ->take((int) config('job_search.shared.max_jobs_per_post'))
            ->map(fn ($job) => [
                'title' => Str::limit(trim((string) ($job['title'] ?? '')), 250, ''),
                'company' => Str::limit(trim((string) ($job['company'] ?? '')), 250, '') ?: null,
                'location' => Str::limit(trim((string) ($job['location'] ?? '')), 250, '') ?: null,
                'description' => Str::limit(trim((string) ($job['description'] ?? '')), 20_000, '') ?: null,
                'apply_email' => Str::of((string) ($job['apply_email'] ?? ''))
                    ->trim()
                    ->lower()
                    ->toString(),
                'apply_link' => trim((string) ($job['apply_link'] ?? '')),
                'apply_instructions' => Str::limit(trim((string) ($job['apply_instructions'] ?? '')), 2000, '') ?: null,
                'deadline' => trim((string) ($job['deadline'] ?? '')),
            ])
            ->filter(fn (array $job) => $job['title'] !== '')
            ->map(fn (array $job) => [
                ...$job,
                'apply_email' => filter_var($job['apply_email'], FILTER_VALIDATE_EMAIL) && $inPost($job['apply_email'])
                    ? $job['apply_email']
                    : null,
                'apply_link' => preg_match('#^https?://#i', $job['apply_link']) && $inPost($job['apply_link'])
                    ? Str::limit($job['apply_link'], 2000, '')
                    : null,
                'deadline' => $this->date($job['deadline']),
            ])
            ->values();
    }

    /**
     * @param  array<string, mixed>  $job
     */
    protected function posting(JobSeekerProfile $profile, array $job): JobPosting
    {
        return JobPosting::firstOrCreate(
            ['fingerprint' => JobPosting::fingerprint(JobPosting::SOURCE_SHARED, $profile->user_id, $job['title'], (string) $job['company'], (string) $job['location'])],
            [
                'source' => JobPosting::SOURCE_SHARED,
                'user_id' => $profile->user_id,
                'title' => $job['title'],
                'company' => $job['company'],
                'location' => $job['location'],
                'description' => $job['description'],
                'url' => $job['apply_link'],
                'apply_email' => $job['apply_email'],
                'apply_instructions' => $job['apply_instructions'],
                'posted_at' => now(),
                'valid_through' => $job['deadline'],
                'details_fetched_at' => now(),
            ],
        );
    }

    protected function date(string $value): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::parse($value)->endOfDay();
        } catch (Throwable) {
            return null;
        }
    }
}
