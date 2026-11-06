<?php

namespace App\Jobs;

use App\Services\Stories\CareerNewsStoryPoster;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Posts one career field's daily news story (see CareerNewsStoryPoster).
 * Dispatched by PostCareerNewsStoriesJob.
 */
class PostCareerNewsStoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    /**
     * Not retried, so a half-failed run can't post the story twice.
     */
    public int $tries = 1;

    /**
     * An AI search, checking each link and drawing the cards.
     */
    public int $timeout = 180;

    public function __construct(public string $field) {}

    public function handle(CareerNewsStoryPoster $poster): void
    {
        $poster->run($this->field);
    }
}
