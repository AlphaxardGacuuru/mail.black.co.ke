<?php

namespace App\Jobs;

use App\Models\JobSeekerProfile;
use App\Services\Stories\CareerFieldsTagger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * The day's news stories: tags any CV that's new since it was last tagged,
 * then posts one story for each career field job seekers are in, so people
 * in similar roles share a story. The fields' searches are spread out so
 * they don't trip Gemini's rate limit together. Scheduled in
 * routes/console.php.
 */
class PostCareerNewsStoriesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    /**
     * A short AI call for each CV that needs tagging.
     */
    public int $timeout = 900;

    public function __construct()
    {
        $this->onConnection('redis-browser');
        $this->onQueue('long');
    }

    public function handle(CareerFieldsTagger $tagger): void
    {
        JobSeekerProfile::query()
            ->where('auto_apply', true)
            ->whereNotNull('parsed_at')
            ->get()
            ->flatMap(fn (JobSeekerProfile $profile) => $tagger->tag($profile))
            ->unique()
            ->values()
            ->each(fn (string $field, int $index) => PostCareerNewsStoryJob::dispatch($field)
                ->delay(now()->addMinutes($index)));
    }
}
