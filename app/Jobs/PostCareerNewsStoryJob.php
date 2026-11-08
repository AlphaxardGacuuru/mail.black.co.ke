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
     * Retried when Gemini is rate limited. The story is only saved at the
     * very end, so a retry can't post it twice.
     */
    public int $tries = 4;

    /**
     * @var array<int, int>
     */
    public array $backoff = [300, 900, 1800];

    /**
     * An AI search, checking each link and drawing the cards.
     */
    public int $timeout = 180;

    public function __construct(public string $field)
    {
        $this->onConnection('redis-browser');
        $this->onQueue('long');
    }

    public function handle(CareerNewsStoryPoster $poster): void
    {
        $poster->run($this->field);
    }
}
