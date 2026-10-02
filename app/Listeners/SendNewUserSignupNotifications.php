<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\AdminNewUserSignupNotification;
use Illuminate\Auth\Events\Registered;

class SendNewUserSignupNotifications
{
    public function handle(Registered $event): void
    {
        $admin = User::where('email', config('admin.email'))->first();

        if ($admin && $admin->isNot($event->user)) {
            $admin->notify(new AdminNewUserSignupNotification($event->user));
        }
    }
}
