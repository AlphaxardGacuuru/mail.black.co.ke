<?php

namespace App\Listeners;

use App\Enums\JobApplicationStatus;
use App\Events\MailMessageReceivedEvent;
use App\Models\JobApplication;

/**
 * An employer's reply lands in the application's thread like any other
 * mail; this marks the application as replied.
 */
class JobApplicationReplyListener
{
	/**
	 * Handle the event.
	 */
	public function handle(MailMessageReceivedEvent $event): void
	{
		JobApplication::query()
			->where('user_id', $event->mailMessage->user_id)
			->where('mail_thread_id', $event->mailMessage->mail_thread_id)
			->where('status', JobApplicationStatus::APPLIED)
			->update([
				'status' => JobApplicationStatus::REPLIED,
				'replied_at' => now(),
			]);
	}
}
