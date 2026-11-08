<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobApplicationResource;
use App\Http\Services\SharedJobPostService;
use App\Jobs\SendJobApplicationsJob;
use App\Models\JobApplication;
use App\Services\Jobs\JobSalaryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

class JobApplicationController extends Controller
{
    /**
     * The tabs on the applications page and the statuses each one shows.
     */
    private const TABS = [
        'review' => [JobApplicationStatus::PENDING_APPROVAL],
        'queued' => [JobApplicationStatus::QUEUED],
        'sent' => [JobApplicationStatus::APPLIED, JobApplicationStatus::REPLIED],
        'manual' => [JobApplicationStatus::APPLY_MANUALLY],
        'closed' => [JobApplicationStatus::SKIPPED, JobApplicationStatus::FAILED],
    ];

    public function index(Request $request): AnonymousResourceCollection
    {
        $tab = $request->validate(['tab' => 'nullable|in:'.implode(',', array_keys(self::TABS))])['tab'] ?? 'review';
        $user = $request->user();

        $applications = $user->jobApplications()
            ->whereIn('status', self::TABS[$tab])
            ->with('posting.board')
            ->orderByDesc($tab === 'sent' ? 'applied_at' : 'created_at')
            ->paginate(20);

        return JobApplicationResource::collection($applications)->additional([
            'counts' => $this->tabCounts($request),
        ]);
    }

    /**
     * The whole job, as its posting describes it, and what it pays: the
     * AI prices it in the background the first time anyone opens it.
     */
    public function show(Request $request, JobSalaryService $salaryService, string $id): JsonResponse
    {
        $posting = $request->user()
            ->jobApplications()
            ->with('posting')
            ->findOrFail($id)
            ->posting;

        abort_unless($posting, 404);

        $salaryService->request($posting);

        return response()->json([
            'description' => $posting->description,
            'industry' => $posting->industry,
            'closesAt' => $posting->valid_through,
            'salary' => $posting->salary,
            'salaryStatus' => match (true) {
                $posting->salary !== null => 'ready',
                $salaryService->pending($posting) => 'estimating',
                default => 'unknown',
            },
        ]);
    }

    /**
     * How many applications each tab holds, for the nav badges.
     */
    public function counts(Request $request): JsonResponse
    {
        return response()->json(['counts' => $this->tabCounts($request)]);
    }

    /**
     * Add the jobs in a post the user shared (from WhatsApp, Telegram and
     * the like): its text, a link, or images of it.
     */
    public function storeShared(Request $request, SharedJobPostService $sharedJobPostService): JsonResponse
    {
        $data = $request->validate([
            'text' => 'nullable|string|max:'.config('job_search.shared.max_text_chars'),
            'url' => 'nullable|url:http,https|max:2000',
            'images' => 'nullable|array|max:'.config('job_search.shared.max_images'),
            'images.*' => 'image|max:'.config('job_search.shared.max_image_kilobytes'),
        ]);

        $text = collect([$data['text'] ?? null, $data['url'] ?? null])
            ->map(fn (?string $part) => trim((string) $part))
            ->filter()
            ->implode("\n\n");

        if ($text === '' && empty($data['images'])) {
            throw ValidationException::withMessages(['post' => ['Paste the job post or add a picture of it.']]);
        }

        $applications = $sharedJobPostService->add($request->user(), $text, $data['images'] ?? []);

        return response()->json([
            'message' => $applications->count() === 1 ? 'Job Added' : "{$applications->count()} Jobs Added",
            'data' => JobApplicationResource::collection($applications->each->load('posting.board')),
        ]);
    }

    /**
     * Save edits to a draft.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id, [JobApplicationStatus::PENDING_APPROVAL]);

        $application->update($this->draftFields($request));

        return response()->json([
            'message' => 'Draft Saved',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    /**
     * Approve a draft (with any last edits): it's queued and goes out within
     * the daily allowance and sending hours.
     */
    public function approve(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id, [JobApplicationStatus::PENDING_APPROVAL]);
        $profile = $request->user()->jobSeekerProfile;

        if (! $profile?->isReady()) {
            throw ValidationException::withMessages([
                'application' => ['Turn on job search in Settings before approving applications.'],
            ]);
        }

        $fields = $this->draftFields($request);

        if (! ($fields['subject'] ?? $application->subject) || ! ($fields['cover_letter'] ?? $application->cover_letter)) {
            throw ValidationException::withMessages([
                'application' => ['This application has no draft yet. Try again in a minute.'],
            ]);
        }

        $application->update([...$fields, 'status' => JobApplicationStatus::QUEUED]);

        SendJobApplicationsJob::dispatch($profile->id);

        return response()->json([
            'message' => 'Application Approved',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    public function skip(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id, [
            JobApplicationStatus::PENDING_APPROVAL,
            JobApplicationStatus::QUEUED,
            JobApplicationStatus::APPLY_MANUALLY,
        ]);

        $application->update(['status' => JobApplicationStatus::SKIPPED]);

        return response()->json([
            'message' => 'Application Skipped',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    /**
     * @param  array<int, JobApplicationStatus>  $statuses
     */
    protected function application(Request $request, string $id, array $statuses): JobApplication
    {
        $application = $request->user()->jobApplications()->findOrFail($id);

        if (! in_array($application->status, $statuses, true)) {
            throw ValidationException::withMessages([
                'application' => ['This application can no longer be changed.'],
            ]);
        }

        return $application;
    }

    /**
     * @return array<string, string>
     */
    protected function draftFields(Request $request): array
    {
        $data = $request->validate([
            'subject' => 'sometimes|required|string|max:250',
            'coverLetter' => 'sometimes|required|string|max:5000',
        ]);

        return array_filter([
            'subject' => isset($data['subject']) ? trim($data['subject']) : null,
            'cover_letter' => isset($data['coverLetter']) ? trim($data['coverLetter']) : null,
        ]);
    }

    /**
     * @return \Illuminate\Support\Collection<string, int>
     */
    private function tabCounts(Request $request)
    {
        $counts = $request->user()
            ->jobApplications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return collect(self::TABS)->map(
            fn (array $statuses) => collect($statuses)->sum(fn (JobApplicationStatus $status) => (int) ($counts[$status->value] ?? 0))
        );
    }
}
