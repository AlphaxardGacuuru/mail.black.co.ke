<?php

namespace App\Listeners;

use App\Events\PaymentCreatedEvent;

class PaymentCreatedListener
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle(PaymentCreatedEvent $event)
    {
        $event
            ->payment
            ->userUnit
            ->user
            ->notify(new PaymentNotification($event->payment));
    }
}
