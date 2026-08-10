<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReferralSavedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $referral;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($referral)
    {
        $this->referral = $referral;
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
        return (new MailMessage)
            ->from("al@property.black.co.ke", "Alphaxard from Black Property")
            ->subject('Referral Saved')
            ->greeting('Congratulations '.$notifiable->name.',')
            ->line('Your Referral '.$this->referral->referee->name.' has successfully created an Account!')
            ->line('You will receive proceeds whenever they pay their subscription.')
            ->line('You can now view their details in the referral list.')
            ->action('View', url('/super/referrals'))
            ->line('Thank you for choosing Black Property!');
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
            "url" => "/super/referrals",
            "from" => $this->referral->referer->name,
            "message" => "Your Referral ".$this->referral->referee->name." has been successfully saved!",
        ];
    }
}
