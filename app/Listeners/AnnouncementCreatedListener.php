<?php

namespace App\Listeners;

use App\Events\AnnouncementCreatedEvent;
use App\Notifications\AnnouncementNotification;

class AnnouncementCreatedListener
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
    public function handle(AnnouncementCreatedEvent $event)
    {
        $event
            ->announcement
            ->announcementUserUnits
            ->each(function ($announcementUserUnits) use ($event) {
                $announcementUserUnits
                    ->userUnit
                    ->user
                    ->notify(new AnnouncementNotification($event->announcement));
            });
    }
}
