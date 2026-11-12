<?php

namespace App\Notifications;

use App\Models\MailMessage as SentMessage;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * A message the user sent never reached its recipient: it failed to send
 * or the recipient's mail server refused it.
 */
class MailNotDeliveredNotification extends Notification implements ShouldQueue
{
	use Queueable, RespectsNotificationPreferences;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(
		protected SentMessage $mailMessage,
		protected ?string $reason = null,
	) {}

	/**
	 * The config/notifications.php category, whose channels the user chooses.
	 */
	protected function category(): string
	{
		return 'mail_not_delivered';
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
			'from' => 'Message Not Delivered',
			'message' => $this->summary(),
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
			->title('Message Not Delivered')
			->icon('/notification-icon-192x192.png')
			->body($this->summary())
			->openAction('message', 'Open Message', $this->url())
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
		return (new MailMessage)
			->subject('Message Not Delivered: '.$this->subject())
			->greeting("Hello {$notifiable->name},")
			->line("Your message **{$this->subject()}** to {$this->recipients()} was not delivered.")
			->when($this->reason, fn (MailMessage $mail, string $reason) => $mail
				->line('The reason given: '.Str::limit($reason, 300)))
			->line('Check the address and send it again from the message.')
			->action('Open Message', url($this->url()));
	}

	protected function summary(): string
	{
		return "\"{$this->subject()}\" to {$this->recipients()} wasn't delivered.";
	}

	protected function subject(): string
	{
		return $this->mailMessage->subject ?: '(no subject)';
	}

	protected function recipients(): string
	{
		return collect([...($this->mailMessage->to ?? []), ...($this->mailMessage->cc ?? []), ...($this->mailMessage->bcc ?? [])])
			->pluck('address')
			->filter()
			->unique()
			->implode(', ');
	}

	protected function url(): string
	{
		return "/mail/sent/{$this->mailMessage->mail_thread_id}";
	}
}
