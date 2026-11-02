<?php

namespace App\Jobs;

use App\Models\JobBoard;
use App\Services\Jobs\JobBoardSource;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reads one listing of a board: its main listing, or one sitemap out of a
 * sitemap index ($lastModified is that sitemap's version).
 */
class FetchJobBoardListingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 45;

    public function __construct(public string $boardId, public string $url, public ?string $lastModified = null) {}

    public function handle(JobBoardSource $source): void
    {
        $board = JobBoard::query()
            ->enabled()
            ->find($this->boardId);

        if (! $board) {
            return;
        }

        $isMainListing = $this->url === $board->listing_url;

        try {
            $result = $source->list($board, $this->url);
        } catch (Throwable $exception) {
            Log::warning('Job board listing failed', ['board' => $board->host, 'url' => $this->url, 'error' => $exception->getMessage()]);

            if ($isMainListing) {
                $board->update(['last_error' => mb_substr($exception->getMessage(), 0, 250)]);
            }

            return;
        }

        $source->markSitemapRead($this->url, $this->lastModified);

        $delay = (int) config('job_search.boards.delay_ms');

        collect($result['sitemaps'])
            ->keys()
            ->each(fn (string $url, int $index) => self::dispatch($board->id, $url, $result['sitemaps'][$url])
                ->delay(now()->addMilliseconds(($index + 1) * $delay)));

        if ($isMainListing) {
            $board->update(['last_listed_at' => now(), 'last_error' => null]);
        }
    }
}
