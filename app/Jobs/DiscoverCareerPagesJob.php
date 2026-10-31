<?php

namespace App\Jobs;

use App\Http\Services\Jobs\CareerPageDiscovery;
use App\Models\JobSeekerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class DiscoverCareerPagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 58;

    public function __construct(public string $profileId) {}

    public function handle(CareerPageDiscovery $discovery): void
    {
        $profile = JobSeekerProfile::with('user')->find($this->profileId);

        if ($profile) {
            $discovery->discover($profile);
        }
    }
}
