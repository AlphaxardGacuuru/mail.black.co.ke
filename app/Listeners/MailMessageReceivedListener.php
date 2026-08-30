<?php

namespace App\Listeners;

use App\Events\MailMessageReceivedEvent;
use App\Notifications\NewMailReceivedNotification;

class MailMessageReceivedListener
{
	/**
	 * Handle the event.
	 */
	public function handle(MailMessageReceivedEvent $event): void
	{
		$event
			->mailMessage
			->user
			?->notify(new NewMailReceivedNotification($event->mailMessage));
	}
}
