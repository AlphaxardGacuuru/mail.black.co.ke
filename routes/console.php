<?php

use App\Jobs\DeleteStaleTemporaryUploadsJob;
use App\Jobs\GenerateInvoicesJob;
use App\Jobs\SendInvoiceRemindersJob;
use App\Jobs\SendSubscriptionExpiryRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prune Telescope entries older than 30 days
Schedule::command('telescope:prune --hours=720')->daily();

// TODO: `websockets:clean` came from the old laravel-websockets package,
// which this project no longer uses (replaced by Reverb). Remove or swap
// in a Reverb-equivalent cleanup task, then re-enable.
// Schedule::command('websockets:clean')->weekly();

Schedule::job(new GenerateInvoicesJob)
    // ->everyMinute()
    ->dailyAt("08:00")
    // ->emailOutputTo("al@property.black.co.ke")
    // ->emailOutputOnFailure("al@property.black.co.ke")
    ->onSuccess(function () {
        Log::info("GenerateInvoicesJob Completed Successfully at " . now());
    })
    ->onFailure(function () {
        Log::error("GenerateInvoicesJob Failed at " . now());
    });

Schedule::job(new SendInvoiceRemindersJob)
    // ->everyMinute()
    ->dailyAt("08:05")
    // ->emailOutputTo("al@property.black.co.ke")
    // ->emailOutputOnFailure("al@property.black.co.ke")
    ->onSuccess(function () {
        Log::info("SendInvoiceRemindersJob completed successfully.");
    })
    ->onFailure(function () {
        Log::error("SendInvoiceRemindersJob failed.");
    });

Schedule::job(new SendSubscriptionExpiryRemindersJob)
    ->dailyAt("09:00")
    ->onSuccess(function () {
        Log::info("SendSubscriptionExpiryRemindersJob completed successfully.");
    })
    ->onFailure(function () {
        Log::error("SendSubscriptionExpiryRemindersJob failed.");
    });

Schedule::command('contracts:expire')
    ->dailyAt("08:10")
    ->onSuccess(function () {
        Log::info("ExpireContractsCommand completed successfully at " . now());
    })
    ->onFailure(function () {
        Log::error("ExpireContractsCommand failed at " . now());
    });

Schedule::job(new DeleteStaleTemporaryUploadsJob)
    // ->everyMinute();
    ->dailyAt("01:00");
