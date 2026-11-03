<?php

namespace App\Notifications\Concerns;

use App\Models\User;
use NotificationChannels\WebPush\WebPushChannel;

/**
 * Sends a notification only on the channels the user picked for its
 * category in Settings → Notifications (see config/notifications.php).
 */
trait RespectsNotificationPreferences
{
	/**
	 * The config/notifications.php category this notification belongs to.
	 */
	abstract protected function category(): string;

	/**
	 * The channels this class delivers. Narrowed when another class sends
	 * the rest, e.g. a queued email beside an instant in-app alert.
	 *
	 * @return array<int, string>
	 */
	protected function handledChannels(): array
	{
		return ['in_app', 'push', 'email'];
	}

	/**
	 * Get the notification's delivery channels.
	 *
	 * @param  mixed  $notifiable
	 * @return array<int, string>
	 */
	public function via($notifiable)
	{
		$drivers = [
			'in_app' => ['database', 'broadcast'],
			'push' => [WebPushChannel::class],
			'email' => ['mail'],
		];

		return collect($this->handledChannels())
			->filter(fn (string $channel) => ! $notifiable instanceof User
				|| $notifiable->wantsNotification($this->category(), $channel))
			->flatMap(fn (string $channel) => $drivers[$channel])
			->values()
			->all();
	}
}
