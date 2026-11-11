<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;

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
        return [WebPushChannel::class];
    }

    protected function bodyLine(): string
    {
        return "New user signed up: {$this->newUser->name} ({$this->newUser->email}).";
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('New user signup')
            ->icon('/notification-icon-192x192.png')
            ->badge('/notification-badge-192x192.png')
            ->body($this->bodyLine())
            ->signedAction('verify', 'Verify', 'push-actions.users.verify', [
                'user' => $notifiable->id,
                'subject' => $this->newUser->id,
            ])
            ->openAction('users', 'View Users', '/admin/users')
            ->data(['url' => '/admin/users']);
    }
}
