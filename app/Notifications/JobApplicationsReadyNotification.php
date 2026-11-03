<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushMessage;

class JobApplicationsReadyNotification extends Notification
{
	use RespectsNotificationPreferences;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected int $count) {}

	/**
	 * The config/notifications.php category, whose channels the user chooses.
	 */
	protected function category(): string
	{
		return 'job_applications_ready';
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
			'url' => '/job-applications',
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
			->body('Review them before they are sent.')
			->data(['url' => '/job-applications'])
			->options(['TTL' => 3600]);
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject($this->title())
			->greeting("Hello {$notifiable->name},")
			->line($this->count.' '.Str::plural('job', $this->count).' matched your CV, and '.($this->count === 1 ? 'its application is' : 'their applications are').' drafted.')
			->line('Nothing is sent until you approve it.')
			->action('Review Applications', url('/job-applications'));
	}

	protected function title(): string
	{
		return $this->count.' '.Str::plural('Application', $this->count).' Ready To Review';
	}
}
