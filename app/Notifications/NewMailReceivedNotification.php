<?php

namespace App\Notifications;

use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Models\MailgunAccount;
use App\Models\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushMessage;

class NewMailReceivedNotification extends Notification
{
	use RespectsNotificationPreferences;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected MailMessage $mailMessage) {}

	/**
	 * The user's mailbox that received the message, resolved once.
	 */
	protected MailgunAccount|false|null $account = false;

	/**
	 * The config/notifications.php category, whose channels the user chooses.
	 */
	protected function category(): string
	{
		return 'new_mail';
	}

	/**
	 * This is sent inside the inbound webhook, so it only does the instant
	 * channels; NewMailReceivedEmailNotification sends the email from the
	 * queue, where a failed send can't make Mailgun redeliver the message.
	 *
	 * @return array<int, string>
	 */
	protected function handledChannels(): array
	{
		return ['in_app', 'push'];
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
			'mailbox' => $this->account()?->mailbox_address,
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
			->icon('/notification-icon-192x192.png')
			->body($this->body())
			->data(['url' => $this->url()])
			->options(['TTL' => 300]);
	}

	protected function from(): string
	{
		return $this->mailMessage->from_address['name']
			?? $this->mailMessage->from_address['address']
			?? 'Unknown sender';
	}

	protected function body(): string
	{
		$subject = $this->mailMessage->subject ?: '(no subject)';
		$mailbox = $this->account()?->mailbox_address;

		return $mailbox ? "{$subject}\nTo {$mailbox}" : $subject;
	}

	/**
	 * The thread link carries the receiving account, so opening it switches
	 * to that account when another one is active.
	 */
	protected function url(): string
	{
		$url = '/mail/' . $this->mailMessage->mail_thread_id . '/show';
		$account = $this->account();

		return $account ? "{$url}?account={$account->id}" : $url;
	}

	protected function account(): ?MailgunAccount
	{
		if ($this->account === false) {
			$this->account = $this->mailMessage->receivingAccount();
		}

		return $this->account;
	}
}
