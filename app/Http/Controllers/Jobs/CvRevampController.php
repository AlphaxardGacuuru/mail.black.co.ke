<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\CvRevampStatus;
use App\Enums\JobApplicationStatus;
use App\Enums\TailoredCvStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CvRevampResource;
use App\Http\Resources\JobApplicationResource;
use App\Models\CvRevamp;
use App\Models\JobApplication;
use App\Models\User;
use App\Services\Cv\CvRenderer;
use App\Services\Cv\CvRevampService;
use App\Services\Cv\CvTailorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Revamping the user's CV with the AI: start (or redo with answers),
 * review each section, use it for applications, and tailor it to a job.
 */
class CvRevampController extends Controller
{
    public function __construct(
        protected CvRevampService $revampService,
        protected CvTailorService $tailorService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->state($request->user()));
    }

    /**
     * Revamp the CV in the background: the first time, or again with the
     * user's answers.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->jobSeekerProfile;

        if (! $profile?->cv_path || ! $profile->parsed_at) {
            throw ValidationException::withMessages(['cv' => ['Upload your CV and wait for it to be read first.']]);
        }

        if ($this->current($user)?->status === CvRevampStatus::DRAFTING) {
            throw ValidationException::withMessages(['cv' => ['Your CV is already being revamped.']]);
        }

        $this->revampService->start($profile);

        return response()->json([
            'message' => 'Revamping CV',
            ...$this->state($user->fresh()),
        ]);
    }

    /**
     * Which version of each section to keep, edits, and answers.
     */
    public function update(Request $request): JsonResponse
    {
        $revamp = $this->revamp($request);

        $data = $request->validate([
            'sections' => 'array|max:30',
            'sections.*.keep' => 'in:revamped,original',
            'sections.*.text' => 'nullable|string|max:1500',
            'sections.*.bullets' => 'nullable|array|max:12',
            'sections.*.bullets.*' => 'nullable|string|max:400',
            'answers' => 'array|max:20',
            'answers.*' => 'nullable|string|max:'.config('job_search.cv_revamp.max_answer_chars'),
        ]);

        $this->revampService->update($revamp, $data['sections'] ?? [], $data['answers'] ?? []);

        return response()->json([
            'message' => 'Changes Saved',
            ...$this->state($request->user()),
        ]);
    }

    /**
     * Send the revamped CV, as it stands, to employers from now on.
     */
    public function use(Request $request): JsonResponse
    {
        $this->revampService->use($this->revamp($request));

        return response()->json([
            'message' => 'Revamped CV In Use',
            ...$this->state($request->user()),
        ]);
    }

    public function stopUsing(Request $request): JsonResponse
    {
        $this->revampService->stopUsing($this->revamp($request));

        return response()->json([
            'message' => 'Original CV In Use',
            ...$this->state($request->user()),
        ]);
    }

    /**
     * Whether every application gets a CV tailored to its job.
     */
    public function updateTailoring(Request $request): JsonResponse
    {
        $data = $request->validate(['tailorCv' => 'required|boolean']);
        $user = $request->user();

        if ($data['tailorCv'] && ! $this->current($user)?->inUse()) {
            throw ValidationException::withMessages(['tailorCv' => ['Use your revamped CV first: tailoring starts from it.']]);
        }

        $user->jobSeekerProfile
            ->fill(['tailor_cv' => $data['tailorCv']])
            ->save();

        return response()->json([
            'message' => 'Settings Saved',
            ...$this->state($user->fresh()),
        ]);
    }

    /**
     * Tailor the CV to one application's job.
     */
    public function tailor(Request $request, string $id): JsonResponse
    {
        $application = $request->user()
            ->jobApplications()
            ->with('posting.board')
            ->whereIn('status', [JobApplicationStatus::PENDING_APPROVAL, JobApplicationStatus::QUEUED, JobApplicationStatus::APPLY_MANUALLY])
            ->findOrFail($id);

        if (! $this->current($request->user())?->inUse()) {
            throw ValidationException::withMessages(['cv' => ['Use your revamped CV first: tailoring starts from it.']]);
        }

        if ($application->cv_status === TailoredCvStatus::TAILORING) {
            throw ValidationException::withMessages(['cv' => ['Your CV is already being tailored to this job.']]);
        }

        $this->tailorService->request($application);

        return response()->json([
            'message' => 'Tailoring CV',
            'data' => JobApplicationResource::make($application),
        ]);
    }

    /**
     * The revamped CV as it stands, by signed link so it can open in a new
     * tab.
     */
    public function download(CvRenderer $renderer, string $id, string $format): Response
    {
        $revamp = CvRevamp::query()
            ->whereNotNull('document')
            ->findOrFail($id);

        return response($format === 'pdf' ? $renderer->pdf($revamp->chosen()) : $renderer->docx($revamp->chosen()), 200, [
            'Content-Type' => $format === 'pdf'
                ? 'application/pdf'
                : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => ($format === 'pdf' ? 'inline' : 'attachment').'; filename="'.$revamp->fileName($format).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /**
     * The CV tailored to one application, by signed link.
     */
    public function downloadTailored(string $id): BinaryFileResponse
    {
        $application = JobApplication::query()
            ->with('user.cvRevamp')
            ->findOrFail($id);

        abort_unless($application->hasTailoredCv(), 404);

        return response()->file(Storage::disk('local')->path($application->cv_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.($application->user->cvRevamp?->fileName('pdf') ?? 'CV.pdf').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    protected function revamp(Request $request): CvRevamp
    {
        $revamp = $this->current($request->user());

        abort_unless($revamp?->status === CvRevampStatus::READY, 404);

        return $revamp;
    }

    /**
     * Read afresh: it changes in the background while the AI works.
     */
    protected function current(User $user): ?CvRevamp
    {
        return $user->load('cvRevamp')->cvRevamp;
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(User $user): array
    {
        $profile = $user->jobSeekerProfile;
        $revamp = $this->current($user);

        return [
            'revamp' => $revamp ? CvRevampResource::make($revamp)->resolve() : null,
            'cvName' => $profile?->cv_original_name,
            'canRevamp' => (bool) ($profile?->cv_path && $profile->parsed_at),
            'tailorCv' => (bool) $profile?->tailor_cv,
        ];
    }
}
