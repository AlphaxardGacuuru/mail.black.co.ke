<?php

namespace App\Http\Controllers\Jobs;

use App\Enums\ApplicationFormStatus;
use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\JobApplicationResource;
use App\Jobs\PrepareApplicationFormJob;
use App\Jobs\SubmitApplicationFormJob;
use App\Models\JobApplication;
use App\Services\Jobs\ApplicationForms\ApplicationFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Applying on the employer's own form, for matches without an apply email:
 * prepare reads the form and suggests answers, the user edits them, and
 * submit fills the form in and sends it.
 */
class JobApplicationFormController extends Controller
{
    public function __construct(protected ApplicationFormService $applicationFormService) {}

    /**
     * Reads the form (again) in the background.
     */
    public function prepare(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id);

        if (in_array($application->form_status, [ApplicationFormStatus::PREPARING, ApplicationFormStatus::SUBMITTING], true)) {
            throw ValidationException::withMessages(['form' => ['This form is already being worked on.']]);
        }

        if (! $request->user()->jobSeekerProfile?->cv_path) {
            throw ValidationException::withMessages(['form' => ['Upload your CV in Settings first.']]);
        }

        $application->update([
            'form_status' => ApplicationFormStatus::PREPARING,
            'form_error' => null,
        ]);

        PrepareApplicationFormJob::dispatch($application->id);

        return response()->json([
            'message' => 'Preparing Application',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id, [ApplicationFormStatus::READY, ApplicationFormStatus::SUBMIT_FAILED]);

        $this->applicationFormService->saveAnswers($application, $this->answers($request));

        return response()->json([
            'message' => 'Answers Saved',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    /**
     * Saves any last edits and submits the form in the background.
     */
    public function submit(Request $request, string $id): JsonResponse
    {
        $application = $this->application($request, $id, [ApplicationFormStatus::READY, ApplicationFormStatus::SUBMIT_FAILED]);

        $this->applicationFormService->saveAnswers($application, $this->answers($request));

        $unanswered = $this->applicationFormService->unanswered($application);

        if ($unanswered->isNotEmpty()) {
            throw ValidationException::withMessages([
                'answers' => ['Answer these first: '.$unanswered->take(5)->implode('; ')],
            ]);
        }

        if (($application->form['captcha'] ?? null) && ! config('job_search.application_forms.captcha.key')) {
            throw ValidationException::withMessages([
                'form' => ['This form has a CAPTCHA, which only you can fill in. Copy your answers and apply on the site.'],
            ]);
        }

        $application->update([
            'form_status' => ApplicationFormStatus::SUBMITTING,
            'form_error' => null,
        ]);

        SubmitApplicationFormJob::dispatch($application->id);

        return response()->json([
            'message' => 'Submitting Application',
            'data' => JobApplicationResource::make($application->load('posting.board')),
        ]);
    }

    /**
     * A screenshot the browser took, by signed link (an <img> can't send
     * the bearer token).
     */
    public function screenshot(string $id, string $name): BinaryFileResponse
    {
        $application = JobApplication::query()->findOrFail($id);

        abort_unless($this->applicationFormService->hasScreenshot($application, $name), 404);

        return response()->file($this->applicationFormService->screenshotPath($application, $name), [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * @param  array<int, ApplicationFormStatus>|null  $formStatuses
     */
    protected function application(Request $request, string $id, ?array $formStatuses = null): JobApplication
    {
        $application = $request->user()
            ->jobApplications()
            ->with('posting.board')
            ->findOrFail($id);

        if ($application->status !== JobApplicationStatus::APPLY_MANUALLY
            || ($formStatuses && ! in_array($application->form_status, $formStatuses, true))) {
            throw ValidationException::withMessages([
                'form' => ['This application can no longer be changed.'],
            ]);
        }

        return $application;
    }

    /**
     * @return array<string, ?string>
     */
    protected function answers(Request $request): array
    {
        return $request->validate([
            'answers' => 'sometimes|array|max:200',
            'answers.*' => 'nullable|string|max:'.config('job_search.application_forms.max_answer_chars'),
        ])['answers'] ?? [];
    }
}
