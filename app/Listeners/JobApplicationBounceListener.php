<?php

namespace App\Listeners;

use App\Enums\JobApplicationStatus;
use App\Enums\MailStatus;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Http\Services\Jobs\JobApplicationSender;
use App\Models\JobApplication;

/**
 * A bounced application is marked failed, and too many bounces switch
 * job search off to protect the user's sending reputation.
 */
class JobApplicationBounceListener
{
	public function __construct(protected JobApplicationSender $sender) {}

	/**
	 * Handle the event.
	 */
	public function handle(MailMessageStatusUpdatedEvent $event): void
	{
		$failed = [
			MailStatus::FAILED->value,
			MailStatus::BOUNCED->value,
			MailStatus::PERMANENT_FAILED->value,
			MailStatus::COMPLAINED->value,
		];

		if (! in_array($event->status, $failed, true)) {
			return;
		}

		$application = JobApplication::query()
			->where('mail_message_id', $event->messageId)
			->whereIn('status', [JobApplicationStatus::APPLIED, JobApplicationStatus::REPLIED])
			->first();

		if (! $application) {
			return;
		}

		$application->update(['status' => JobApplicationStatus::FAILED]);

		$profile = $application->user?->jobSeekerProfile;

		if ($profile?->auto_apply) {
			$this->sender->pauseIfBouncing($profile);
		}
	}
}
