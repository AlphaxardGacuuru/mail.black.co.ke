<?php

namespace App\Jobs;

use App\Enums\TailoredCvStatus;
use App\Models\JobApplication;
use App\Services\Cv\CvTailorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class TailorCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public function __construct(public string $applicationId)
    {
        $this->onConnection('redis-browser');
        $this->onQueue('long');
    }

    public function handle(CvTailorService $service): void
    {
        $application = JobApplication::with(['posting', 'user.cvRevamp'])->find($this->applicationId);

        if ($application?->cv_status === TailoredCvStatus::TAILORING) {
            $service->tailor($application);
        }
    }

    public function failed(?Throwable $exception): void
    {
        JobApplication::query()
            ->whereKey($this->applicationId)
            ->where('cv_status', TailoredCvStatus::TAILORING)
            ->update(['cv_status' => TailoredCvStatus::FAILED]);
    }
}
