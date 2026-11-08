<?php

namespace App\Jobs;

use App\Models\JobPosting;
use App\Services\Jobs\JobSalaryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class EstimateJobSalaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public string $postingId) {}

    public function handle(JobSalaryService $service): void
    {
        $posting = JobPosting::find($this->postingId);

        if ($posting && $service->pending($posting)) {
            $service->estimate($posting);
        }
    }
}
