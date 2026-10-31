<?php

namespace App\Listeners;

use App\Events\JobApplicationSentEvent;
use App\Notifications\JobApplicationSentNotification;

class JobApplicationSentListener
{
	/**
	 * Handle the event.
	 */
	public function handle(JobApplicationSentEvent $event): void
	{
		$event
			->application
			->user
			?->notify(new JobApplicationSentNotification($event->application));
	}
}
