<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\VonageMessage;
use Illuminate\Notifications\Notification;

class PaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $payment;

    protected $pdf;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($payment, $pdf)
    {
        $this->payment = $payment;
        $this->pdf = $pdf;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->cc('alphaxardgacuuru47@gmail.com')
            ->subject("Payment Received")
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Your Payment of KES '.number_format($this->payment->amount).' has been Received!')
            ->line('Receipt Number: '.$this->payment->number)
            ->action('View', url('/tenant/payments/'.$this->payment->id.'/show'))
            ->line("Thank you for your tenancy!")
            ->salutation('Regards, '.$this->payment->userUnit->unit->property->name)
            ->attachData($this->pdf, "{$this->payment->number}.pdf", [
                'mime' => 'application/pdf',
            ]);
    }

    /**
     * Get the Vonage / SMS representation of the notification.
     */
    public function toVonage(object $notifiable): VonageMessage
    {
        return (new VonageMessage)
            ->content('Hello '.$notifiable->name.','
                .' Your Payment of KES '.number_format($this->payment->amount).' has been Received!'
                .' Thank you for choosing Black Property!')
            ->from(config('services.vonage.sms_from'));
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
            "url" => "/tenant/dashboard",
            "from" => "",
            "message" => "Payment of KES ".number_format($this->payment->amount)." received.",
        ];
    }
}
