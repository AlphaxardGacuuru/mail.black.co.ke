<?php

namespace App\Notifications;

use App\Models\UserSubscriptionPlan;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiryNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected UserSubscriptionPlan $userSubscriptionPlan,
        protected int $daysRemaining
    ) {}

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
        $planName = $this->userSubscriptionPlan->subscriptionPlan?->name ?? 'subscription plan';
        $expiryDate = $this->userSubscriptionPlan->end_date?->format('d M Y');

        $daysLine = $this->daysRemaining === 0
            ? 'Your subscription expires today.'
            : 'Your subscription expires in '.$this->daysRemaining.' day'.($this->daysRemaining === 1 ? '' : 's').'.';

        return (new MailMessage)
            ->greeting('Hello '.$notifiable->name.',')
            ->subject('Subscription Expiry Reminder')
            ->line('This is a reminder for your '.$planName.'.')
            ->line($daysLine)
            ->line('Expiry date: '.$expiryDate)
            ->line('Please renew to avoid service interruption.');
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
            'userSubscriptionPlanId' => $this->userSubscriptionPlan->id,
            'subscriptionPlanId' => $this->userSubscriptionPlan->subscription_plan_id,
            'subscriptionPlanName' => $this->userSubscriptionPlan->subscriptionPlan?->name,
            'endDate' => $this->userSubscriptionPlan->end_date?->toISOString(),
            'daysRemaining' => $this->daysRemaining,
            'message' => $this->daysRemaining === 0
                ? 'Your subscription expires today.'
                : 'Your subscription expires in '.$this->daysRemaining.' day'.($this->daysRemaining === 1 ? '' : 's').'.',
        ];
    }
}
