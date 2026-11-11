<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Good matches that can't be applied to by email, so the user has to apply
 * on the employer's site themselves.
 */
class JobsToApplyManuallyNotification extends Notification
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
		return 'jobs_to_apply_manually';
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
			'url' => '/job-applications/manual',
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
			->body('These employers take applications on their own sites.')
			->openAction('see', 'See Jobs', '/job-applications/manual')
			->data(['url' => '/job-applications/manual'])
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
			->line($this->count.' '.Str::plural('job', $this->count).' matched your CV, but the '.Str::plural('employer', $this->count).' only '.($this->count === 1 ? 'takes' : 'take').' applications on '.($this->count === 1 ? 'its own site' : 'their own sites').'.')
			->line('Open each one to apply there yourself.')
			->action('See Jobs', url('/job-applications/manual'));
	}

	protected function title(): string
	{
		return $this->count.' '.Str::plural('Job', $this->count).' To Apply For Yourself';
	}
}
