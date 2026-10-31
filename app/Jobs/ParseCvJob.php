<?php

namespace App\Jobs;

use App\Agents\CvProfileAgent;
use App\Models\JobSeekerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Files\Document;
use Throwable;

class ParseCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 58;

    public function __construct(public string $profileId, public string $cvHash) {}

    public function handle(): void
    {
        $profile = JobSeekerProfile::find($this->profileId);

        // A newer CV was uploaded since this was queued.
        if (! $profile?->cv_path || $profile->cv_hash !== $this->cvHash) {
            return;
        }

        try {
            $response = CvProfileAgent::make()->prompt(
                'Summarise this CV.',
                attachments: [Document::fromStorage($profile->cv_path, 'local')],
                model: config('job_search.model'),
            );
        } catch (Throwable $exception) {
            Log::warning('CV parsing failed', ['profile_id' => $profile->id, 'error' => $exception->getMessage()]);

            return;
        }

        $profile->update([
            'profile' => $response->toArray(),
            'parsed_at' => now(),
        ]);
    }
}
