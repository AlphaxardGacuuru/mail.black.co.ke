<?php

namespace App\Jobs;

use App\Http\Services\Jobs\JobApplicationSender;
use App\Models\JobSeekerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends at most one queued application per user per run, so a day's
 * applications go out spread across working hours rather than in a burst.
 */
class SendJobApplicationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function __construct(public ?string $profileId = null) {}

    public function handle(JobApplicationSender $sender): void
    {
        JobSeekerProfile::query()
            ->where('auto_apply', true)
            ->when($this->profileId, fn ($query) => $query->whereKey($this->profileId))
            ->with('user')
            ->each(fn (JobSeekerProfile $profile) => $sender->sendNext($profile));
    }
}
