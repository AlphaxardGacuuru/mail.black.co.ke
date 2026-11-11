<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Models\JobApplication;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An application sent on the user's behalf bounced, so the employer never
 * got it and the user may want to apply another way.
 */
class JobApplicationFailedNotification extends Notification implements ShouldQueue
{
	use Queueable, RespectsNotificationPreferences;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected JobApplication $application) {}

	/**
	 * The config/notifications.php category, whose channels the user chooses.
	 */
	protected function category(): string
	{
		return 'job_application_failed';
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return array<string, mixed>
	 */
	public function toArray($notifiable)
	{
		return [
			'url' => '/job-applications/closed',
			'from' => 'Job Search',
			'message' => $this->title(),
		];
	}

	/**
	 * Get the web push representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		return (new WebPushMessage)
			->title($this->title())
			->icon('/notification-icon-192x192.png')
			->body("{$this->application->posting->apply_email} did not accept it. You may want to apply another way.")
			->when($this->application->posting->url, fn (WebPushMessage $message, string $url) => $message
				->openAction('posting', 'Open Posting', $url))
			->openAction('applications', 'View Applications', '/job-applications/closed')
			->data(['url' => '/job-applications/closed'])
			->options(['TTL' => 86400]);
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toMail($notifiable): MailMessage
	{
		$posting = $this->application->posting;

		return (new MailMessage)
			->subject($this->title())
			->greeting("Hello {$notifiable->name},")
			->line("The application we sent for you to {$posting->apply_email} for **{$posting->title}**"
				.($posting->company ? " at **{$posting->company}**" : '').' was not delivered.')
			->line('The address may no longer exist, or the employer\'s mail server refused it.')
			->when($posting->url, fn (MailMessage $mail) => $mail
				->line("If you are still interested, check the posting for another way to apply: {$posting->url}"))
			->action('View Applications', url('/job-applications/closed'));
	}

	protected function title(): string
	{
		$posting = $this->application->posting;

		return 'Application Not Delivered: '.$posting->title.($posting->company ? ' At '.$posting->company : '');
	}
}
