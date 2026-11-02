<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The first reply from an employer to an application. The reply already
 * raises the usual new-mail notification in the app and by web push, so
 * this only emails the user's own address, which they're more likely to
 * check.
 */
class JobApplicationRepliedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected JobApplication $application) {}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		return ['mail'];
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
