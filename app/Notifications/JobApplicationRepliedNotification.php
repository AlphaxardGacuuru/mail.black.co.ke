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
 * The first reply from an employer to an application. The reply already
 * raises the usual new-mail notification in the app and by web push, so
 * by default this only emails the user's own address, which they're more
 * likely to check.
 */
class JobApplicationRepliedNotification extends Notification implements ShouldQueue
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
		return 'job_application_replied';
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
			'url' => $this->url(),
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
			->body('Read the reply in your inbox.')
			->openAction('read', 'Read Reply', $this->url())
			->openAction('applications', 'View Applications', '/job-applications/sent')
			->data(['url' => $this->url()])
			->options(['TTL' => 3600]);
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
			->line(($posting->company ?: 'An employer')." replied to your application for **{$posting->title}**.")
			->line('Read the reply and respond from your Black Mail inbox.')
			->action('Read Reply', url($this->url()));
	}

	protected function title(): string
	{
		$posting = $this->application->posting;

		return ($posting->company ?: 'Employer').' Replied: '.$posting->title;
	}

	protected function url(): string
	{
		return '/mail/'.$this->application->mail_thread_id.'/show';
	}
}
