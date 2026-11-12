<?php

namespace App\Listeners;

use App\Enums\MailStatus;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Models\JobApplication;
use App\Models\MailMessage;
use App\Notifications\MailNotDeliveredNotification;

/**
 * Tells the user when a message they sent didn't reach its recipient, so
 * they don't take the Sent folder's word for it.
 */
class MailNotDeliveredListener
{
	/**
	 * Handle the event.
	 */
	public function handle(MailMessageStatusUpdatedEvent $event): void
	{
		$undelivered = [
			MailStatus::FAILED->value,
			MailStatus::BOUNCED->value,
			MailStatus::PERMANENT_FAILED->value,
		];

		// A job application that bounces has its own notification
		// (see JobApplicationBounceListener).
		if (! in_array($event->status, $undelivered, true)
			|| JobApplication::where('mail_message_id', $event->messageId)->exists()) {
			return;
		}

		$message = MailMessage::query()
			->with('user')
			->find($event->messageId);

		$message?->user?->notify(new MailNotDeliveredNotification($message, $event->errorMessage));
	}
}
