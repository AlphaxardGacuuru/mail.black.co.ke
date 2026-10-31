<?php

namespace App\Notifications;

use App\Models\JobApplication;
use Illuminate\Notifications\Notification;

class JobApplicationSentNotification extends Notification
{
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
		$posting = $this->application->posting;

		return [
			'url' => $this->application->mail_thread_id
				? '/mail/'.$this->application->mail_thread_id.'/show'
				: '/job-applications',
			'from' => 'Job Search',
			'message' => 'Applied To '.$posting->title.($posting->company ? ' At '.$posting->company : ''),
		];
	}
}
