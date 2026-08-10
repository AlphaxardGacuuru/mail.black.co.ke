<?php

namespace App\Jobs;

use App\Models\UserSubscriptionPlan;
use App\Notifications\SubscriptionExpiryNotification;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSubscriptionExpiryRemindersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $today = Carbon::today();
        $threeDaysFromNow = $today->copy()->addDays(3);

        $plans = UserSubscriptionPlan::query()
            ->with(['user', 'subscriptionPlan'])
            ->where('status', 'active')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $threeDaysFromNow)
            ->orderBy('end_date')
            ->get()
            ->unique('user_id')
            ->values();

        $plans->each(function (UserSubscriptionPlan $plan) use ($today) {
            $daysRemaining = $today->diffInDays($plan->end_date->copy()->startOfDay(), false);

            if ($daysRemaining < 0 || $daysRemaining > 3) {
                return;
            }

            $plan->user->notify(new SubscriptionExpiryNotification($plan, $daysRemaining));
        });
    }
}
