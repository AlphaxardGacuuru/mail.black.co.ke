<?php

namespace App\Notifications;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $contract;

    protected $pdf;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($contract, $pdf)
    {
        $this->contract = $contract;
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
            ->subject("Contract {$this->contract->number}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new contract has been created for you.')
            ->line('Contract Number: '.$this->contract->number)
            ->line('Month: '.Carbon::create()->month($this->contract->month)->format('F'))
            ->line('Year: '.$this->contract->year)
            ->action('View Contract', url('/tenant/contracts/'.$this->contract->id.'/show'))
            ->line("Welcome to ".$this->contract->userUnit->unit->property->name."!")
            ->salutation('Regards, '.$this->contract->userUnit->unit->property->name)
            ->attachData($this->pdf, "{$this->contract->number}.pdf", [
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
        //
    }
}
