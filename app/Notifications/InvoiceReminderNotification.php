<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceReminderNotification extends Notification
{
    use Queueable;

    protected $invoice;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($invoice)
    {
        $this->invoice = $invoice;
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
        $type = ucwords(str_replace('_', ' ', $this->invoice->type));

        $balance = number_format($this->invoice->balance);

        $month = Carbon::create()
            ->month($this->invoice->month)
            ->format('F'); // "March"

        return (new MailMessage)
            ->from("al@property.black.co.ke", "Alphaxard from Black Property")
            ->subject($type.' Invoice Reminder')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('This is a reminder that your '.$type.' Invoice of KES '.$balance.' for the month of '.$month.' is due.')
            ->action('View Invoice', url('/tenant/invoices/'.$this->invoice->id.'/show'))
            ->line('Thank you for choosing Black Property!')
            ->line('If you have any questions, feel free to contact us.');
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
