<?php

use App\Http\Controllers\Settings\MailgunCredentialsController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', fn() => view('app'))->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::patch('settings/mailgun-credentials', [MailgunCredentialsController::class, 'update'])->name('mailgun-credentials.update');
    Route::delete('settings/mailgun-credentials', [MailgunCredentialsController::class, 'destroy'])->name('mailgun-credentials.destroy');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', fn() => view('app'))->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', fn() => view('app'))->name('appearance.edit');
});
