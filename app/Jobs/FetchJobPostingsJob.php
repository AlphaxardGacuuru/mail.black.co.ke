<?php

namespace App\Jobs;

use App\Http\Services\Jobs\MyJobMagSource;
use App\Models\JobPosting;
use App\Models\JobSeekerProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads myjobmag's feed, then queues the new job pages to be read one at a
 * time, spaced out so the site isn't hammered.
 */
class FetchJobPostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function handle(MyJobMagSource $source): void
    {
        // Nobody to find jobs for: don't touch the site at all.
        if (! JobSeekerProfile::where('auto_apply', true)->exists()) {
            return;
        }

        try {
            $source->syncFeed();
        } catch (Throwable $exception) {
            Log::warning('Job feed fetch failed', ['error' => $exception->getMessage()]);

            return;
        }

        $delay = (int) config('job_search.myjobmag.delay_ms');

        JobPosting::query()
            ->where('source', JobPosting::SOURCE_MYJOBMAG)
            ->whereNull('details_fetched_at')
            ->orderByDesc('posted_at')
            ->limit((int) config('job_search.myjobmag.details_per_run'))
            ->pluck('id')
            ->each(fn (string $id, int $index) => FetchJobPostingDetailsJob::dispatch($id)
                ->delay(now()->addMilliseconds($index * $delay)));
    }
}
