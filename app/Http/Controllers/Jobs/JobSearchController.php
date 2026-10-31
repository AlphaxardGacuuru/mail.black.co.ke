<?php

namespace App\Http\Controllers\Jobs;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyCareerPageResource;
use App\Http\Resources\JobSeekerProfileResource;
use App\Http\Services\Jobs\JobApplicationSender;
use App\Http\Services\Jobs\SafePageFetcher;
use App\Jobs\DiscoverCareerPagesJob;
use App\Jobs\MatchJobsForUserJob;
use App\Jobs\ParseCvJob;
use App\Jobs\SyncCareerPageJob;
use App\Models\CompanyCareerPage;
use App\Models\JobSeekerProfile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Job search settings: the toggle and consent, the CV, preferences and
 * the company careers pages to watch.
 */
class JobSearchController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->profile($user);

        $data = $request->validate([
            'autoApply' => 'required|boolean',
            'consent' => 'boolean',
            'mode' => 'required|in:review,autopilot',
            'mailgunAccountId' => ['nullable', 'string', Rule::exists('mailgun_accounts', 'id')->where('user_id', $user->id)],
            'preferences.keywords' => 'array|max:20',
            'preferences.keywords.*' => 'string|max:60',
            'preferences.locations' => 'array|max:10',
            'preferences.locations.*' => 'string|max:60',
            'preferences.remoteOk' => 'boolean',
            'preferences.excludedCompanies' => 'array|max:50',
            'preferences.excludedCompanies.*' => 'string|max:120',
            'dailyLimit' => 'required|integer|min:1|max:'.config('job_search.max_daily_limit'),
            'minMatchScore' => 'required|integer|min:'.config('job_search.min_match_score_floor').'|max:100',
        ]);

        $consentedAt = $profile->consented_at ?? (($data['consent'] ?? false) ? now() : null);

        $profile->fill([
            'mode' => $data['mode'],
            'mailgun_account_id' => $data['mailgunAccountId'] ?? null,
            'consented_at' => $consentedAt,
            'preferences' => [
                'keywords' => $this->clean($data['preferences']['keywords'] ?? []),
                'locations' => $this->clean($data['preferences']['locations'] ?? []),
                'remote_ok' => (bool) ($data['preferences']['remoteOk'] ?? false),
                'excluded_companies' => $this->clean($data['preferences']['excludedCompanies'] ?? []),
            ],
            'daily_limit' => $data['dailyLimit'],
            'min_match_score' => $data['minMatchScore'],
        ]);

        $turningOn = $data['autoApply'] && ! $profile->auto_apply;

        if ($data['autoApply']) {
            $this->ensureCanTurnOn($profile);
            $profile->fill(['auto_apply' => true, 'paused_reason' => null]);
        }

        $profile->save();

        if (! $data['autoApply'] && $profile->auto_apply) {
            JobApplicationSender::stop($profile);
        }

        if ($turningOn) {
            MatchJobsForUserJob::dispatch($profile->id);
            $user->companyCareerPages()->active()->whereNull('last_fetched_at')->pluck('id')
                ->each(fn (string $id) => SyncCareerPageJob::dispatch($id));

            if ($profile->last_discovery_at === null || $profile->last_discovery_at->lte(now()->subDays(config('job_search.discovery.interval_days')))) {
                DiscoverCareerPagesJob::dispatch($profile->id);
            }
        }

        return response()->json([
            'message' => 'Settings Saved',
            ...$this->state($user->fresh()),
        ]);
    }

    public function uploadCv(Request $request): JsonResponse
    {
        $request->validate([
            'cv' => 'required|file|mimes:pdf|mimetypes:application/pdf|max:'.config('job_search.max_cv_kilobytes'),
        ]);

        $user = $request->user();
        $profile = $this->profile($user);
        $file = $request->file('cv');
        $hash = hash_file('sha256', $file->getRealPath());

        // The same file again: keep the profile already read from it.
        if ($hash === $profile->cv_hash && $profile->cv_path) {
            return response()->json(['message' => 'CV Uploaded', ...$this->state($user)]);
        }

        $previous = $profile->cv_path;
        $path = $file->storeAs("job-search/cv/{$user->id}", Str::random(24).'.pdf', 'local');

        $profile->fill([
            'cv_path' => $path,
            'cv_original_name' => Str::limit($file->getClientOriginalName(), 200, ''),
            'cv_mime_type' => 'application/pdf',
            'cv_hash' => $hash,
            'profile' => null,
            'parsed_at' => null,
        ])->save();

        if ($previous) {
            Storage::disk('local')->delete($previous);
        }

        ParseCvJob::dispatch($profile->id, $hash);

        return response()->json(['message' => 'CV Uploaded', ...$this->state($user)]);
    }

    /**
     * Delete the CV, profile, career pages and applications. Mail already
     * sent stays in the mailbox like any other mail.
     */
    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user) {
            $profile = $user->jobSeekerProfile;

            if ($profile?->cv_path) {
                Storage::disk('local')->delete($profile->cv_path);
            }

            $user->jobApplications()->delete();
            $user->companyCareerPages()->delete();
            $profile?->delete();
        });

        return response()->json(['message' => 'Job Search Data Deleted', ...$this->state($user->fresh())]);
    }

    public function storeCareerPage(Request $request, SafePageFetcher $fetcher): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['url' => 'required|url:http,https|max:2000']);

        $added = $user->companyCareerPages()->active()->where('source', CompanyCareerPage::SOURCE_USER);

        if ($added->count() >= config('job_search.max_career_pages')) {
            throw ValidationException::withMessages([
                'url' => ['You can watch up to '.config('job_search.max_career_pages').' careers pages.'],
            ]);
        }

        try {
            $fetcher->assertPublicUrl($data['url']);
            $fetcher->assertAllowedByRobots($data['url']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['url' => [$exception->getMessage()]]);
        }

        $page = $user->companyCareerPages()->firstOrNew(['url_hash' => hash('sha256', $data['url'])]);
        $isNew = ! $page->exists || $page->dismissed_at !== null;

        // Adding a page by hand also brings back one the search found and
        // was removed.
        $page->fill([
            'url' => $data['url'],
            'host' => CompanyCareerPage::hostOf($data['url']),
            'source' => CompanyCareerPage::SOURCE_USER,
            'dismissed_at' => null,
            'last_error' => $isNew ? null : $page->last_error,
        ])->save();

        if ($isNew && $user->jobSeekerProfile?->auto_apply) {
            SyncCareerPageJob::dispatch($page->id);
        }

        return response()->json(['message' => 'Careers Page Added', ...$this->state($user)]);
    }

    /**
     * Pages added by hand are deleted; pages the search found are hidden
     * instead, so the next search doesn't add them back.
     */
    public function destroyCareerPage(Request $request, string $id): JsonResponse
    {
        $page = $request->user()->companyCareerPages()->active()->findOrFail($id);

        $page->source === CompanyCareerPage::SOURCE_SEARCH
            ? $page->update(['dismissed_at' => now()])
            : $page->delete();

        return response()->json(['message' => 'Careers Page Removed', ...$this->state($request->user())]);
    }

    protected function profile(User $user): JobSeekerProfile
    {
        return $user->jobSeekerProfile()->firstOrCreate([], [
            'daily_limit' => config('job_search.default_daily_limit'),
            'min_match_score' => config('job_search.default_min_match_score'),
            'preferences' => [],
        ]);
    }

    protected function ensureCanTurnOn(JobSeekerProfile $profile): void
    {
        $errors = array_filter([
            'consent' => $profile->consented_at ? null : 'Agree to let the app apply for jobs on your behalf.',
            'cv' => $profile->parsed_at ? null : 'Upload your CV and wait for it to be read first.',
            'mailgunAccountId' => $profile->sendingAccount()?->mailbox_address ? null : 'Add a mail account to send applications from.',
        ]);

        if ($errors) {
            throw ValidationException::withMessages(array_map(fn ($message) => [$message], $errors));
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(User $user): array
    {
        $profile = $user->jobSeekerProfile;

        return [
            'profile' => $profile ? JobSeekerProfileResource::make($profile)->resolve() : null,
            'careerPages' => CompanyCareerPageResource::collection(
                $user->companyCareerPages()->active()->orderBy('source', 'desc')->latest()->get()
            )->resolve(),
            'limits' => [
                'maxDailyLimit' => config('job_search.max_daily_limit'),
                'minMatchScoreFloor' => config('job_search.min_match_score_floor'),
                'maxCareerPages' => config('job_search.max_career_pages'),
                'maxDiscoveredPages' => config('job_search.discovery.max_pages'),
                'maxCvKilobytes' => config('job_search.max_cv_kilobytes'),
            ],
        ];
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    protected function clean(array $values): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $values))));
    }
}
