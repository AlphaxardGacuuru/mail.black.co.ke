<?php

namespace App\Notifications;

use App\Models\Story;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A new story the user can see, like the day's news for their career field.
 */
class NewStoryNotification extends Notification implements ShouldQueue
{
	use Queueable, RespectsNotificationPreferences;

	/**
	 * Create a new notification instance.
	 */
	public function __construct(protected Story $story) {}

	/**
	 * The config/notifications.php category, whose channels the user chooses.
	 */
	protected function category(): string
	{
		return 'new_story';
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
			'from' => $this->title(),
			'message' => $this->body(),
		];
	}

	/**
	 * Get the web push representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toWebPush($notifiable, $notification): WebPushMessage
	{
		$card = $this->story->media[0]['image'] ?? null;

		return (new WebPushMessage)
			->title($this->title())
			->icon($this->story->user->avatar ?? '/notification-icon-192x192.png')
			->badge('/notification-badge-192x192.png')
			->body($this->body())
			// The first card, shown large where the system supports it.
			->when($card, fn (WebPushMessage $message, string $path) => $message
				->image(Storage::disk('public')->url($path)))
			->tag('story-'.$this->story->id)
			->openAction('view', 'View Story', $this->url())
			->data(['url' => $this->url()])
			// Not delivered once the story has expired.
			->options(['TTL' => max(60, (int) now()->diffInSeconds($this->story->created_at->addDay()))]);
	}

	/**
	 * Get the mail representation of the notification.
	 *
	 * @param  mixed  $notifiable
	 */
	public function toMail($notifiable): MailMessage
	{
		return (new MailMessage)
			->subject("{$this->title()}: {$this->story->text}")
			->greeting("Hello {$notifiable->name},")
			->line($this->body())
			->line('Stories are up for 24 hours.')
			->action('View Story', url($this->url()));
	}

	/**
	 * Field news is personal to whoever gets it (as in StoryResource).
	 */
	protected function title(): string
	{
		return $this->story->career_field ? 'News for you' : $this->story->user->name;
	}

	/**
	 * "Software & IT: Safaricom opens its API platform, and 3 more".
	 */
	protected function body(): string
	{
		$titles = collect($this->story->media)
			->pluck('title')
			->filter();

		return Str::of((string) $this->story->text)
			->when($titles->isNotEmpty(), fn ($body) => $body
				->append(': '.Str::limit($titles->first(), 100))
				->when($titles->count() > 1, fn ($body) => $body->append(', and '.($titles->count() - 1).' more')))
			->trim(': ')
			->toString() ?: 'New story';
	}

	/**
	 * The story's page (resources/js/lib/stories.ts).
	 */
	protected function url(): string
	{
		return "/chats/stories/{$this->story->id}/view";
	}
}
