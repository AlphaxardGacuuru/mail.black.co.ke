<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Services\Jobs\JobBoardSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchJobPostingDetailsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(public string $postingId) {}

    public function handle(JobBoardSource $source): void
    {
        $posting = JobPosting::query()
            ->whereNull('details_fetched_at')
            ->find($this->postingId);

        if (! $posting) {
            return;
        }

        try {
            if (! $source->fetchDetails($posting)) {
                Log::info('Job page had no job data', ['url' => $posting->url]);
            }
        } catch (Throwable $exception) {
            Log::info('Job page fetch failed', ['url' => $posting->url, 'error' => $exception->getMessage()]);

            // Keep what the listing gave rather than retrying the page
            // forever; without an apply email it can only be applied to
            // manually.
            $posting->update(['details_fetched_at' => now()]);
        }
    }
}
