<?php

namespace App\Listeners;

use App\Events\MpesaTransactionCreatedEvent;
use App\Notifications\MpesaTransactionCreatedNotification;

class MpesaTransactionCreatedListener
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
    public function handle(MpesaTransactionCreatedEvent $event)
    {
        $event
            ->mpesaTransaction
            ->user
            ->notify(new MpesaTransactionCreatedNotification($event->mpesaTransaction));
    }
}
