<?php

namespace App\Jobs;

use App\Models\JobBoard;
use App\Models\JobSeekerProfile;
use App\Services\Jobs\JobBoardSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * For every enabled board: read its listing for new job links, and read the
 * job pages listed last time, one at a time and spaced out so no board is
 * hammered. Boards are different sites, so they run side by side.
 */
class FetchJobPostingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 50;

    public function handle(JobBoardSource $source): void
    {
        // Nobody to find jobs for: don't touch the boards at all.
        if (! JobSeekerProfile::where('auto_apply', true)->exists()) {
            return;
        }

        $delay = (int) config('job_search.boards.delay_ms');
        $aiPagesLeft = $source->aiPagesLeftToday();

        JobBoard::query()
            ->enabled()
            ->get()
            ->each(function (JobBoard $board) use ($delay, &$aiPagesLeft) {
                FetchJobBoardListingJob::dispatch($board->id, $board->listing_url);

                // Pages the AI reads come out of one daily allowance.
                $limit = $board->reader === JobBoard::READER_AI
                    ? min((int) config('job_search.boards.details_per_run'), $aiPagesLeft)
                    : (int) config('job_search.boards.details_per_run');

                $board->postings()
                    ->whereNull('details_fetched_at')
                    ->where(fn ($query) => $query
                        ->whereNull('posted_at')
                        ->orWhere('posted_at', '>=', now()->subDays(config('job_search.max_posting_age_days'))))
                    ->orderByDesc('posted_at')
                    ->limit($limit)
                    ->pluck('id')
                    ->tap(function ($ids) use ($board, &$aiPagesLeft) {
                        if ($board->reader === JobBoard::READER_AI) {
                            $aiPagesLeft -= $ids->count();
                        }
                    })
                    ->each(fn (string $id, int $index) => FetchJobPostingDetailsJob::dispatch($id)
                        ->delay(now()->addMilliseconds(($index + 1) * $delay)));
            });
    }
}
