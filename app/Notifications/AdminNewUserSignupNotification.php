<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/**
 * An operational alert to the admin account, not a user-facing preference,
 * always sent.
 */
class AdminNewUserSignupNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected User $newUser) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database', WebPushChannel::class];
    }

    protected function bodyLine(): string
    {
        return "New user signed up: {$this->newUser->name} ({$this->newUser->email}).";
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->from('al@mail.black.co.ke', 'Alphaxard from Black Mail')
            ->subject('New user signup')
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($this->bodyLine())
            ->action('View users', url('/admin/users'));
    }

    public function toArray($notifiable): array
    {
        return [
            'url' => '/admin/users',
            'from' => 'System',
            'message' => $this->bodyLine(),
        ];
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New user signup')
            ->icon('/notification-badge-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->data(['url' => '/admin/users']);
    }
}
