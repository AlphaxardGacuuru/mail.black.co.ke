<?php

use App\Http\Controllers\Settings\MailgunAccountController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', fn() => view('app'))->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/mailgun-accounts', [MailgunAccountController::class, 'index'])->name('mailgun-accounts.index');
    Route::post('settings/mailgun-accounts', [MailgunAccountController::class, 'store'])->name('mailgun-accounts.store');
    Route::patch('settings/mailgun-accounts/{account}', [MailgunAccountController::class, 'update'])->name('mailgun-accounts.update');
    Route::post('settings/mailgun-accounts/{account}/activate', [MailgunAccountController::class, 'activate'])->name('mailgun-accounts.activate');
    Route::delete('settings/mailgun-accounts/{account}', [MailgunAccountController::class, 'destroy'])->name('mailgun-accounts.destroy');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', fn() => view('app'))->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::get('settings/appearance', fn() => view('app'))->name('appearance.edit');
});
