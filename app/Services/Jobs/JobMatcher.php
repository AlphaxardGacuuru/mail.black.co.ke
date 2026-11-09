<?php

namespace App\Services\Jobs;

use App\Agents\JobApplicationAgent;
use App\Agents\JobMatchAgent;
use App\Enums\JobApplicationStatus;
use App\Jobs\DraftJobApplicationJob;
use App\Models\AiUsage;
use App\Models\JobApplication;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use App\Notifications\JobApplicationsReadyNotification;
use App\Notifications\JobsToApplyManuallyNotification;
use App\Services\AiUsageService;
use App\Services\Cv\CvTailorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds postings worth applying to for one job seeker and drafts the
 * applications. AI is only used on postings that pass cheap filters, in
 * batches, within a daily allowance, and only while the review backlog is
 * small.
 */
class JobMatcher
{
    private const DESCRIPTION_CHARS = 1500;

    public function __construct(
        protected AiUsageService $aiUsageService,
        protected CvTailorService $tailor,
    ) {}

    /**
     * Score up to $batches batches of new postings (one AI call each, kept
     * small so a queued run stays well inside the worker timeout). Returns
     * how many postings were scored.
     */
    public function matchFor(JobSeekerProfile $profile, int $batches = 1): int
    {
        if (! $profile->isReady() || $this->backlog($profile) >= config('job_search.max_pending_drafts')) {
            return 0;
        }

        $this->draftMissing($profile);

        $budget = (int) config('job_search.max_scored_per_day') - $this->scoredToday($profile);

        if ($budget <= 0) {
            return 0;
        }

        $batchSize = (int) config('job_search.match_batch_size');
        $candidates = $this->candidates($profile)->take(min($budget, $batches * $batchSize));
        $matched = 0;
        $manual = 0;

        foreach ($candidates->chunk($batchSize) as $batch) {
            $scores = $this->score($profile, $batch->values());

            if ($scores === null) {
                break;
            }

            foreach ($batch as $posting) {
                $result = $scores[$posting->id] ?? null;

                if ($result === null) {
                    continue;
                }

                $application = $this->record($profile, $posting, $result['score'], $result['reason']);

                if ($application->status === JobApplicationStatus::PENDING_APPROVAL) {
                    DraftJobApplicationJob::dispatch($application->id);
                    $matched++;
                }

                if ($application->status === JobApplicationStatus::APPLY_MANUALLY) {
                    $manual++;
                }
            }
        }

        // On autopilot the user hears about each application as it's sent.
        if ($matched > 0 && ! $profile->isAutopilot()) {
            $profile->user->notify(new JobApplicationsReadyNotification($matched));
        }

        // Good matches without an apply email are never sent for the user,
        // so they need to hear about them whatever the mode.
        if ($manual > 0) {
            $profile->user->notify(new JobsToApplyManuallyNotification($manual));
        }

        return $candidates->count();
    }

    /**
     * Postings not yet seen for this user that pass the keyword, location
     * and excluded-company filters, newest first.
     *
     * @return Collection<int, JobPosting>
     */
    public function candidates(JobSeekerProfile $profile): Collection
    {
        $keywords = collect([...$profile->preference('keywords'), ...($profile->profile['target_titles'] ?? [])])
            ->flatMap(fn (string $phrase) => preg_split('/[^\p{L}\p{N}+#]+/u', mb_strtolower($phrase)))
            ->filter(fn (string $word) => mb_strlen($word) >= 3 && ! in_array($word, ['and', 'the', 'for', 'senior', 'junior', 'officer', 'assistant'], true))
            ->unique()
            ->values();

        // Each place matches as a whole word, ignoring spaces and punctuation
        // inside it: "Murang'a" matches "Muranga", "Homa Bay" matches
        // "Homabay", but "Iten" doesn't match "Kitengela".
        $locations = collect($profile->preference('locations'))
            ->map(fn ($location) => preg_split('//u', preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($location)), -1, PREG_SPLIT_NO_EMPTY))
            ->filter()
            ->map(fn (array $letters) => '/(?<![\p{L}\p{N}])'.implode('[^\p{L}\p{N}]*', array_map(fn ($letter) => preg_quote($letter, '/'), $letters)).'(?![\p{L}\p{N}])/u');
        $remoteOk = (bool) ($profile->preferences['remote_ok'] ?? false);
        $excluded = collect($profile->preference('excluded_companies'))->map(fn ($company) => JobPosting::normalizeCompany($company))->filter();

        // A job posted on several boards is only considered once: not again
        // once it's been seen through any of them.
        $seen = JobPosting::query()
            ->whereNotNull('dedupe_key')
            ->whereHas('applications', fn ($query) => $query->where('user_id', $profile->user_id))
            ->select('dedupe_key');

        return JobPosting::query()
            ->availableTo($profile->user_id)
            ->whereDoesntHave('applications', fn ($query) => $query->where('user_id', $profile->user_id))
            ->where(fn ($query) => $query
                ->whereNull('dedupe_key')
                ->orWhereNotIn('dedupe_key', $seen))
            ->orderByDesc('posted_at')
            ->limit(300)
            ->get()
            // Of the copies of one job, the one with an apply email.
            ->groupBy(fn (JobPosting $posting) => $posting->dedupe_key ?? $posting->id)
            ->map(fn (Collection $copies) => $copies
                ->sortByDesc(fn (JobPosting $posting) => $posting->apply_email !== null)
                ->first())
            ->filter(function (JobPosting $posting) use ($keywords, $locations, $remoteOk, $excluded) {
                if ($excluded->contains(JobPosting::normalizeCompany($posting->company))) {
                    return false;
                }

                $haystack = mb_strtolower($posting->title.' '.Str::limit((string) $posting->description, 3000, ''));

                if ($keywords->isNotEmpty() && ! $keywords->contains(fn ($word) => str_contains($haystack, $word))) {
                    return false;
                }

                if ($locations->isEmpty() || $posting->location === null) {
                    return true;
                }

                $location = mb_strtolower($posting->location);

                return $locations->contains(fn ($pattern) => preg_match($pattern, $location) === 1)
                    || ($remoteOk && (str_contains($location, 'remote') || str_contains($haystack, 'remote')));
            })
            ->values();
    }

    /**
     * @param  Collection<int, JobPosting>  $postings
     * @return array<string, array{score: int, reason: string}>|null null when the AI call failed
     */
    protected function score(JobSeekerProfile $profile, Collection $postings): ?array
    {
        $listing = $postings->map(fn (JobPosting $posting, int $index) => implode("\n", array_filter([
            'id: '.($index + 1),
            "Title: {$posting->title}",
            $posting->company ? "Company: {$posting->company}" : null,
            $posting->location ? "Location: {$posting->location}" : null,
            $posting->industry ? "Field: {$posting->industry}" : null,
            'Description: '.Str::limit((string) $posting->description, self::DESCRIPTION_CHARS, ' [...]'),
        ])))->implode("\n\n---\n\n");

        try {
            $response = JobMatchAgent::make()
                ->prompt(
                    $this->candidateSummary($profile)."\n\nPostings:\n\n".$listing,
                    model: config('job_search.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Job matching failed', ['user_id' => $profile->user_id, 'error' => $exception->getMessage()]);

            return null;
        }

        $this->aiUsageService->record($profile->user_id, AiUsage::FEATURE_JOB_MATCH, $response, $profile);

        $scores = [];

        foreach ((array) ($response['results'] ?? []) as $result) {
            $posting = $postings->get(((int) ($result['id'] ?? 0)) - 1);

            if ($posting) {
                $scores[$posting->id] = [
                    'score' => max(0, min(100, (int) ($result['score'] ?? 0))),
                    'reason' => Str::limit(trim((string) ($result['reason'] ?? '')), 480),
                ];
            }
        }

        return $scores;
    }

    protected function record(JobSeekerProfile $profile, JobPosting $posting, int $score, string $reason): JobApplication
    {
        $threshold = max((int) config('job_search.min_match_score_floor'), $profile->min_match_score);

        $status = match (true) {
            $score < $threshold => JobApplicationStatus::NOT_MATCHED,
            ! $posting->apply_email => JobApplicationStatus::APPLY_MANUALLY,
            $this->appliedToCompanyRecently($profile, $posting) => JobApplicationStatus::SKIPPED,
            default => JobApplicationStatus::PENDING_APPROVAL,
        };

        if ($status === JobApplicationStatus::SKIPPED) {
            $reason = "Already applied to {$posting->company} recently.";
        }

        return JobApplication::create([
            'user_id' => $profile->user_id,
            'job_posting_id' => $posting->id,
            'status' => $status,
            'match_score' => $score,
            'match_reason' => $reason,
        ]);
    }

    /**
     * Add jobs the user shared themselves. They chose these, so the filters
     * and minimum score don't apply; the score is still shown. One with an
     * email to apply to is drafted for review, the rest are to apply for
     * on the employer's site.
     *
     * @param  Collection<int, JobPosting>  $postings
     * @return Collection<int, JobApplication>
     */
    public function addShared(JobSeekerProfile $profile, Collection $postings): Collection
    {
        $scores = $this->score($profile, $postings->values()) ?? [];

        return $postings->map(function (JobPosting $posting) use ($profile, $scores) {
            $recentlyApplied = $posting->apply_email && $this->appliedToCompanyRecently($profile, $posting);

            $application = JobApplication::create([
                'user_id' => $profile->user_id,
                'job_posting_id' => $posting->id,
                'status' => match (true) {
                    ! $posting->apply_email => JobApplicationStatus::APPLY_MANUALLY,
                    $recentlyApplied => JobApplicationStatus::SKIPPED,
                    default => JobApplicationStatus::PENDING_APPROVAL,
                },
                'match_score' => $scores[$posting->id]['score'] ?? null,
                'match_reason' => $recentlyApplied
                    ? "Already applied to {$posting->company} recently."
                    : ($scores[$posting->id]['reason'] ?? null),
            ]);

            if ($application->status === JobApplicationStatus::PENDING_APPROVAL) {
                DraftJobApplicationJob::dispatch($application->id);
            }

            return $application;
        });
    }

    /**
     * Write the subject and cover letter; on autopilot the application is
     * then queued to send, otherwise it waits for the user's approval.
     */
    public function draft(JobSeekerProfile $profile, JobApplication $application): void
    {
        if ($application->status !== JobApplicationStatus::PENDING_APPROVAL || $application->cover_letter !== null) {
            return;
        }

        $letter = $profile->sendsCoverLetterAsWritten()
            ? $this->letterAsWritten($profile, $application->posting)
            : $this->writeLetter($profile, $application);

        if ($letter === null) {
            return;
        }

        // A shared post's apply email was read by the AI, often from an
        // image, so it's always checked by the user first.
        $sendsItself = $profile->isAutopilot() && $application->posting->source !== JobPosting::SOURCE_SHARED;

        $application->update([
            ...$letter,
            'status' => $sendsItself ? JobApplicationStatus::QUEUED : JobApplicationStatus::PENDING_APPROVAL,
        ]);

        if ($profile->tailorsCv()) {
            $this->tailor->request($application);
        }
    }

    /**
     * The user's own letter, unchanged apart from {company} and {role}. No
     * AI is used.
     *
     * @return array{subject: string, cover_letter: string}
     */
    public function letterAsWritten(JobSeekerProfile $profile, JobPosting $posting): array
    {
        return [
            'subject' => Str::limit("Application for {$posting->title}", 250, ''),
            'cover_letter' => Str::of((string) $profile->cover_letter)
                ->replaceMatches('/\{\s*company\s*\}/i', $posting->company ?: 'your organisation')
                ->replaceMatches('/\{\s*(role|title|position)\s*\}/i', $posting->title)
                ->trim()
                ->toString(),
        ];
    }

    /**
     * A letter written by the AI for this job, from the user's own letter
     * when they have one.
     *
     * @return array{subject: string, cover_letter: string}|null
     */
    protected function writeLetter(JobSeekerProfile $profile, JobApplication $application): ?array
    {
        $posting = $application->posting;

        try {
            $response = JobApplicationAgent::make()
                ->prompt(
                    implode("\n\n", array_filter([
                        $this->candidateSummary($profile),
                        $profile->cover_letter
                            ? "The candidate's own cover letter:\n".Str::limit($profile->cover_letter, 5000, '')
                            : null,
                        "Job posting:\n".implode("\n", array_filter([
                            "Title: {$posting->title}",
                            $posting->company ? "Company: {$posting->company}" : null,
                            $posting->location ? "Location: {$posting->location}" : null,
                            'Description: '.Str::limit((string) $posting->description, 4000, ' [...]'),
                            $posting->apply_instructions ? "How to apply: {$posting->apply_instructions}" : null,
                        ])),
                    ])),
                    model: config('job_search.model'),
                );
        } catch (Throwable $exception) {
            Log::warning('Job application draft failed', ['application_id' => $application->id, 'error' => $exception->getMessage()]);

            return null;
        }

        $this->aiUsageService->record($application->user_id, AiUsage::FEATURE_JOB_APPLICATION_DRAFT, $response, $application);

        $subject = Str::limit(trim((string) ($response['subject'] ?? '')), 250, '');
        $body = trim((string) ($response['body'] ?? ''));

        return $subject !== '' && $body !== '' ? ['subject' => $subject, 'cover_letter' => $body] : null;
    }

    /**
     * Retry drafts that failed on an earlier run (not ones still in flight).
     */
    protected function draftMissing(JobSeekerProfile $profile): void
    {
        $profile->user->jobApplications()
            ->where('status', JobApplicationStatus::PENDING_APPROVAL)
            ->whereNull('cover_letter')
            ->where('created_at', '<=', now()->subMinutes(30))
            ->limit(3)
            ->pluck('id')
            ->each(fn (string $id) => DraftJobApplicationJob::dispatch($id));
    }

    public function appliedToCompanyRecently(JobSeekerProfile $profile, JobPosting $posting, ?string $exceptApplicationId = null): bool
    {
        $company = JobPosting::normalizeCompany($posting->company);

        if ($company === '') {
            return false;
        }

        return $profile->user->jobApplications()
            ->when($exceptApplicationId, fn ($query) => $query->whereKeyNot($exceptApplicationId))
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query
                    ->whereIn('status', [
                        JobApplicationStatus::QUEUED,
                        JobApplicationStatus::APPLIED,
                        JobApplicationStatus::REPLIED,
                    ])
                    ->where(fn ($query) => $query
                        ->whereNull('applied_at')
                        ->orWhere('applied_at', '>=', now()->subDays(config('job_search.company_cooldown_days')))))
                // Applied to on the employer's site by the user themselves.
                ->orWhere(fn ($query) => $query
                    ->where('status', JobApplicationStatus::APPLY_MANUALLY)
                    ->where('applied_at', '>=', now()->subDays(config('job_search.company_cooldown_days')))))
            ->with('posting:id,company')
            ->get()
            ->contains(fn (JobApplication $application) => JobPosting::normalizeCompany($application->posting?->company) === $company);
    }

    protected function backlog(JobSeekerProfile $profile): int
    {
        return $profile->user->jobApplications()
            ->whereIn('status', [JobApplicationStatus::PENDING_APPROVAL, JobApplicationStatus::QUEUED])
            ->count();
    }

    protected function scoredToday(JobSeekerProfile $profile): int
    {
        return $profile->user->jobApplications()
            ->where('created_at', '>=', now()->startOfDay())
            ->count();
    }

    protected function candidateSummary(JobSeekerProfile $profile): string
    {
        $cv = $profile->profile ?? [];
        $experience = collect($cv['experience'] ?? [])
            ->map(fn ($role) => "- {$role['title']}, {$role['company']} ({$role['period']})")
            ->implode("\n");

        return implode("\n", array_filter([
            'Candidate:',
            'Name: '.($cv['full_name'] ?? $profile->user->name),
            'Headline: '.($cv['headline'] ?? ''),
            'Summary: '.($cv['summary'] ?? ''),
            'Years of experience: '.($cv['years_of_experience'] ?? 'unknown'),
            'Skills: '.implode(', ', $cv['skills'] ?? []),
            'Target roles: '.implode(', ', [...$profile->preference('keywords'), ...($cv['target_titles'] ?? [])]),
            'Education: '.implode('; ', $cv['education'] ?? []),
            $experience ? "Experience:\n{$experience}" : null,
            'Based in: '.($cv['location'] ?? 'unknown'),
            'Preferred locations: '.(implode(', ', $profile->preference('locations')) ?: 'any')
                .(($profile->preferences['remote_ok'] ?? false) ? ' (remote welcome)' : ''),
            'Will not work for: '.(implode(', ', $profile->preference('excluded_companies')) ?: 'no exclusions'),
        ]));
    }
}
