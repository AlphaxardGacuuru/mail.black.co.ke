<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvoiceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoice;

    protected $pdf;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($invoice, $pdf)
    {
        $this->invoice = $invoice;
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
        $mailMessage = (new MailMessage)
            ->cc('alphaxardgacuuru47@gmail.com')
            ->subject("Invoice {$this->invoice->number}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new invoice has been created for you.')
            ->line('Invoice Number: '.$this->invoice->number)
            ->line('Month: '.Carbon::create()->month($this->invoice->month)->format('F'))
            ->line('Year: '.$this->invoice->year);

        if ($this->invoice->type == "water") {
            $waterReading = $this->invoice->userUnit
                ->waterReadings()
                ->where("month", $this->invoice->month)
                ->where("year", $this->invoice->year)
                ->first()
                ?->reading;

            $waterUsage = $this->invoice->userUnit
                ->waterReadings()
                ->where("month", $this->invoice->month)
                ->where("year", $this->invoice->year)
                ->first()
                ?->usage;

            $mailMessage
                ->line('Water Reading: '.number_format($waterReading).'m³')
                ->line('Water Usage: '.number_format($waterUsage).'m³');
        }

        return $mailMessage
            ->line('Total Amount: KES '.number_format($this->invoice->amount))
            ->action('View Invoice', url('/tenant/invoices/'.$this->invoice->id.'/show'))
            ->line("Thank you for your tenancy!")
            ->salutation('Regards, '.$this->invoice->userUnit->unit->property->name)
            ->attachData($this->pdf, "{$this->invoice->number}.pdf", [
                'mime' => 'application/pdf',
            ]);
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
