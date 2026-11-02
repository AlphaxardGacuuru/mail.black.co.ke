<?php

namespace App\Jobs;

use App\Models\JobSeekerProfile;
use App\Services\Jobs\JobMatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class MatchJobsForUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 58;

    public function __construct(public string $profileId) {}

    public function handle(JobMatcher $matcher): void
    {
        $profile = JobSeekerProfile::with('user')->find($this->profileId);

        if ($profile) {
            $matcher->matchFor($profile);
        }
    }
}
