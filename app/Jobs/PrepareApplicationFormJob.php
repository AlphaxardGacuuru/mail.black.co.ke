<?php

namespace App\Jobs;

use App\Enums\ApplicationFormStatus;
use App\Models\JobApplication;
use App\Services\Jobs\ApplicationForms\ApplicationFormService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Opens the employer's application form in a headless browser and
 * suggests an answer to each question, for the user to review.
 */
class PrepareApplicationFormJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 280;

    public function __construct(public string $applicationId)
    {
        $this->onConnection('redis-browser');
        $this->onQueue('browser');
    }

    public function handle(ApplicationFormService $applicationFormService): void
    {
        $application = JobApplication::with(['posting.board', 'user.jobSeekerProfile'])->find($this->applicationId);

        if ($application?->form_status === ApplicationFormStatus::PREPARING && $application->user->jobSeekerProfile) {
            $applicationFormService->prepare($application);
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobApplication::query()
            ->whereKey($this->applicationId)
            ->where('form_status', ApplicationFormStatus::PREPARING)
            ->update([
                'form_status' => ApplicationFormStatus::FAILED,
                'form_error' => "The application form couldn't be read. Try again, or apply on the site.",
            ]);
    }
}
