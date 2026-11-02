<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * An application sent on the user's behalf bounced, so the employer never
 * got it and the user may want to apply another way.
 */
class JobApplicationFailedNotification extends Notification implements ShouldQueue
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
		return ['database', 'broadcast', WebPushChannel::class, 'mail'];
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
			->icon('/android-chrome-192x192.png')
			->body("{$this->application->posting->apply_email} did not accept it. You may want to apply another way.")
			->data(['url' => '/job-applications'])
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
			->action('View Applications', url('/job-applications'));
	}

	protected function title(): string
	{
		$posting = $this->application->posting;

		return 'Application Not Delivered: '.$posting->title.($posting->company ? ' At '.$posting->company : '');
	}
}
