<?php

namespace App\Notifications;

use App\Models\MailMessage;
use Illuminate\Notifications\Notification;

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
		return ['database', 'broadcast'];
	}

	/**
	 * Get the array representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 * @return array<string, mixed>
	 */
	public function toArray($notifiable)
	{
		$from = $this->mailMessage->from_address['name']
			?? $this->mailMessage->from_address['address']
			?? 'Unknown sender';

		return [
			'url' => '/mail/' . $this->mailMessage->mail_thread_id . '/show',
			'from' => $from,
			'message' => $this->mailMessage->subject ?: '(no subject)',
		];
	}
}
