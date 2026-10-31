<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class JobApplicationsReadyNotification extends Notification
{
	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected int $count) {}

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
			->body('Review them before they are sent.')
			->data(['url' => '/job-applications'])
			->options(['TTL' => 3600]);
	}

	protected function title(): string
	{
		return $this->count.' '.Str::plural('Application', $this->count).' Ready To Review';
	}
}
