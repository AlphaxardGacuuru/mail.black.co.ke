<?php

namespace App\Jobs;

use App\Http\Services\Jobs\CompanyCareerPageSource;
use App\Models\CompanyCareerPage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use Throwable;

class SyncCareerPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 58;

    public function __construct(public string $pageId) {}

    public function handle(CompanyCareerPageSource $source): void
    {
        $page = CompanyCareerPage::find($this->pageId);

        if (! $page) {
            return;
        }

        try {
            $source->sync($page);
        } catch (Throwable $exception) {
            $page->update([
                'last_fetched_at' => now(),
                'last_error' => Str::limit($exception->getMessage(), 250),
            ]);
        }
    }
}
