<?php

use App\Notifications\JobApplicationFailedNotification;
use App\Notifications\JobsToApplyManuallyNotification;
use App\Notifications\WelcomeNotification;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Notifications already sent link to the page they now open on.
     */
    public function up(): void
    {
        collect([
            JobsToApplyManuallyNotification::class => '/job-applications/manual',
            JobApplicationFailedNotification::class => '/job-applications/closed',
            WelcomeNotification::class => '/mail',
        ])->each(fn (string $url, string $type) => DB::table('notifications')
            ->where('type', $type)
            ->update(['data' => DB::raw("JSON_SET(data, '$.url', ".DB::getPdo()->quote($url).')')]));
    }

    public function down(): void
    {
        //
    }
};
