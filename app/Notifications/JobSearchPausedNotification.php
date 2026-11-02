<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class JobSearchPausedNotification extends Notification implements ShouldQueue
{
	use Queueable;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected ?string $reason) {}

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
			'url' => '/settings/job-search',
			'from' => 'Job Search',
			'message' => 'Job Search Paused'.($this->reason ? ': '.$this->reason : ''),
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
			->title('Job Search Paused')
			->icon('/android-chrome-192x192.png')
			->body($this->reason ?? 'Check your settings before turning it back on.')
			->data(['url' => '/settings/job-search'])
			->options(['TTL' => 86400]);
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject('Job Search Paused')
			->greeting("Hello {$notifiable->name},")
			->line('We have stopped sending job applications on your behalf.')
			->when($this->reason, fn (MailMessage $mail) => $mail
				->line("**Why:** {$this->reason}"))
			->line('Applications that were waiting to go out are held back for your review. Nothing more will be sent until you switch job search back on.')
			->action('Check Job Search Settings', url('/settings/job-search'));
	}
}
