<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Str;

/**
 * The email half of a new-mail notification, for users who chose email for
 * new mail. Queued, unlike NewMailReceivedNotification, so it never runs
 * inside the inbound webhook.
 */
class NewMailReceivedEmailNotification extends NewMailReceivedNotification implements ShouldQueue
{
	use Queueable;

	/**
	 * @return array<int, string>
	 */
	protected function handledChannels(): array
	{
		return ['email'];
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toMail($notifiable): MailMessage
	{
		$subject = $this->mailMessage->subject ?: '(no subject)';
		$mailbox = $this->account()?->mailbox_address;
		$excerpt = Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($this->mailMessage->body_text ?: $this->mailMessage->body_html)))), 300);

		return (new MailMessage)
			->subject("New Mail: {$subject}")
			->greeting("Hello {$notifiable->name},")
			->line("**From:** {$this->from()}")
			->when($mailbox, fn (MailMessage $mail) => $mail->line("**To:** {$mailbox}"))
			->line("**Subject:** {$subject}")
			->when($excerpt !== '', fn (MailMessage $mail) => $mail->line($excerpt))
			->action('Open Message', url($this->url()))
			->line('You can choose how you hear about new mail in Settings → Notifications.');
	}
}
