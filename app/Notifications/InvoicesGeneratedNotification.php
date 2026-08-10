<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoicesGeneratedNotification extends Notification
{
    use Queueable;

    protected $result;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($result)
    {
        $this->result = $result;
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
        $notification = (new MailMessage)
            ->greeting('Hello '.$notifiable->name.',')
            ->subject('Generate Invoices Job Completed')
            ->line($this->result->message);

        if ($this->result->isForAdmin) {
            $notification = $notification
                ->line('Users Processed: '.$this->result->users->count());
        }

        $notification = $notification
            ->line('Properties Processed: '.$this->result->properties->count())
            ->line('Units Processed: '.$this->result->units->count())
            ->line('Invoices Generated: '.$this->result->invoices->count());

        if ($this->result->isForAdmin) {
            $notification = $notification
                ->action('View Invoices', url('/super/invoices'));
        } else {
            $notification = $notification
                ->action('View Invoices', url('/admin/invoices'));
        }

        return $notification;
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
            //
        ];
    }
}
