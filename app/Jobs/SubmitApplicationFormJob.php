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
 * Fills in the employer's application form with the answers the user
 * approved, and submits it. Never retried: a second run could send the
 * application twice.
 */
class SubmitApplicationFormJob implements ShouldQueue
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
        $application = JobApplication::with(['posting', 'user.jobSeekerProfile'])->find($this->applicationId);

        if ($application?->form_status === ApplicationFormStatus::SUBMITTING) {
            $applicationFormService->submit($application);
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobApplication::query()
            ->whereKey($this->applicationId)
            ->where('form_status', ApplicationFormStatus::SUBMITTING)
            ->update([
                'form_status' => ApplicationFormStatus::SUBMIT_FAILED,
                'form_error' => 'Submitting stopped part way. Check the site before trying again, in case it went through.',
            ]);
    }
}
