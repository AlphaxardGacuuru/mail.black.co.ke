<?php

namespace App\Listeners;

use App\Events\StoryPostedEvent;
use App\Models\User;
use App\Notifications\NewStoryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tells everyone who can see a new story (see StoryService::visibleTo):
 * the people in its career field, or everyone when it's for no field.
 */
class StoryPostedListener implements ShouldQueue
{
	/**
	 * Handle the event.
	 */
	public function handle(StoryPostedEvent $event): void
	{
		$story = $event->story;

		User::query()
			->whereKeyNot($story->user_id)
			->when($story->career_field, fn (Builder $query, string $field) => $query
				->whereHas('jobSeekerProfile', fn (Builder $profile) => $profile
					->whereJsonContains('career_fields', $field)))
			->lazy()
			->each(fn (User $user) => $user->notify(new NewStoryNotification($story)));
	}
}
