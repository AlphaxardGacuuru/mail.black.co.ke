<?php

namespace App\Notifications;

use App\Models\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class NewMailReceivedNotification extends Notification
{
	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected MailMessage $mailMessage) {}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		return ['database', 'broadcast', WebPushChannel::class];
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
			'from' => $this->from(),
			'message' => $this->mailMessage->subject ?: '(no subject)',
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
			->title($this->from())
			->icon('/favicon.ico')
			->body($this->mailMessage->subject ?: '(no subject)')
			->data(['url' => $this->url()])
			->options(['TTL' => 300]);
	}

	protected function from(): string
	{
		return $this->mailMessage->from_address['name']
			?? $this->mailMessage->from_address['address']
			?? 'Unknown sender';
	}

	protected function url(): string
	{
		return '/mail/' . $this->mailMessage->mail_thread_id . '/show';
	}
}
