<?php

namespace App\Jobs;

use App\Enums\CvRevampStatus;
use App\Models\CvRevamp;
use App\Services\Cv\CvRevampService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

class RevampCvJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 150;

    public function __construct(public string $revampId)
    {
        $this->onConnection('redis-browser');
        $this->onQueue('long');
    }

    public function handle(CvRevampService $service): void
    {
        $revamp = CvRevamp::with('user.jobSeekerProfile')->find($this->revampId);

        if ($revamp?->status === CvRevampStatus::DRAFTING) {
            $service->revamp($revamp);
        }
    }

    public function failed(?Throwable $exception): void
    {
        CvRevamp::query()
            ->whereKey($this->revampId)
            ->where('status', CvRevampStatus::DRAFTING)
            ->update([
                'status' => CvRevampStatus::FAILED,
                'error' => "Your CV couldn't be revamped just now. Try again in a few minutes.",
            ]);
    }
}
