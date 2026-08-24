<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MailgunCredentialsUpdatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct(public bool $removed = false)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        $message = (new MailMessage)
            ->subject('Your Mailgun credentials were '.($this->removed ? 'removed' : 'updated'))
            ->greeting('Hello '.$notifiable->name.',');

        if ($this->removed) {
            $message->line('The Mailgun credentials on your account were just removed. Your mailbox will go back to sending mail through the shared system account.');
        } else {
            $message->line('The Mailgun credentials on your account were just changed. Your mailbox will now send mail through the Mailgun account you configured.');
        }

        return $message
            ->line('If you did not make this change, please secure your account and contact support immediately.')
            ->action('Review Mailgun settings', url('/settings/profile'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'url' => '/settings/profile',
            'message' => $this->removed
                ? 'Your Mailgun credentials were removed.'
                : 'Your Mailgun credentials were updated.',
        ];
    }
}
