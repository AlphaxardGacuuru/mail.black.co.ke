<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobApplicationResource;
use App\Jobs\SendJobApplicationsJob;
use App\Models\JobApplication;
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
            ->with('posting')
            ->orderByDesc($tab === 'sent' ? 'applied_at' : 'created_at')
            ->paginate(20);

        $counts = $user->jobApplications()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return JobApplicationResource::collection($applications)->additional([
            'counts' => collect(self::TABS)->map(
                fn (array $statuses) => collect($statuses)->sum(fn (JobApplicationStatus $status) => (int) ($counts[$status->value] ?? 0))
            ),
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
            'data' => JobApplicationResource::make($application->load('posting')),
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
            'data' => JobApplicationResource::make($application->load('posting')),
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
            'data' => JobApplicationResource::make($application->load('posting')),
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
}
