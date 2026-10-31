<?php

namespace App\Jobs;

use App\Http\Services\Jobs\JobMatcher;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class DraftJobApplicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public string $applicationId) {}

    public function handle(JobMatcher $matcher): void
    {
        $application = JobApplication::with(['posting', 'user.jobSeekerProfile'])->find($this->applicationId);
        $profile = $application?->user?->jobSeekerProfile;

        if ($application && $profile?->isReady()) {
            $matcher->draft($profile, $application);
        }
    }
}
