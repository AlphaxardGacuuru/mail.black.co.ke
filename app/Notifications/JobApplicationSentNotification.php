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
 * Sent every time an application goes out from the user's mailbox on
 * their behalf, on every channel, so nothing is ever sent unseen.
 */
class JobApplicationSentNotification extends Notification implements ShouldQueue
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
		return 'job_application_sent';
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
			->body("Sent to {$this->application->posting->apply_email} with your CV attached.")
			->openAction('view', 'View Application', $this->url())
			->openAction('settings', 'Job Search Settings', '/settings/job-search')
			->data(['url' => $this->url()])
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
			->line('We have just applied for this job on your behalf:')
			->line("**Role:** {$posting->title}")
			->when($posting->company, fn (MailMessage $mail) => $mail
				->line("**Company:** {$posting->company}"))
			->line("**Sent to:** {$posting->apply_email}")
			->line("**Subject:** {$this->application->subject}")
			->when($this->application->match_score !== null, fn (MailMessage $mail) => $mail
				->line("**Match:** {$this->application->match_score}%"))
			->line('Your CV was attached, and any reply from the employer will arrive in your Black Mail inbox.')
			->action('View Application', url($this->url()))
			->line('To stop applications being sent for you, switch job search off in your settings.');
	}

	protected function title(): string
	{
		$posting = $this->application->posting;

		return 'Applied To '.$posting->title.($posting->company ? ' At '.$posting->company : '');
	}

	protected function url(): string
	{
		return $this->application->mail_thread_id
			? '/mail/'.$this->application->mail_thread_id.'/show'
			: '/job-applications/sent';
	}
}
