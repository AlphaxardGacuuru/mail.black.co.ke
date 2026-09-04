<?php

use App\Http\Controllers\Settings\MailgunAccountController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Support\Facades\Route;

// Page shells only — same posture as /mail and friends (see routes/web.php's
// fallback route): no server-side auth guard, since this app authenticates
// via a bearer token in localStorage that a plain browser navigation can't
// carry. The client-side router's requireAuth() guard protects these once
// the SPA boots. Guarding these server-side instead 302s a reload/deep-link
// through /login before the client ever gets a chance to prove it's authed.
Route::redirect('settings', '/settings/profile');
Route::get('settings/profile', fn() => view('app'))->name('profile.edit');
Route::get('settings/mail-accounts', fn() => view('app'))->name('mail-accounts.edit');
Route::get('settings/security', fn() => view('app'))->name('security.edit');
Route::get('settings/appearance', fn() => view('app'))->name('appearance.edit');

// Data endpoints stay behind auth:sanctum — these are real API calls made by
// the SPA via Axios, which does attach the bearer token, so the guard works
// as intended here.
Route::middleware(['auth:sanctum'])->group(function () {
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('settings/mailgun-accounts', [MailgunAccountController::class, 'index'])->name('mailgun-accounts.index');
    Route::post('settings/mailgun-accounts', [MailgunAccountController::class, 'store'])->name('mailgun-accounts.store');
    Route::patch('settings/mailgun-accounts/{account}', [MailgunAccountController::class, 'update'])->name('mailgun-accounts.update');
    Route::post('settings/mailgun-accounts/{account}/activate', [MailgunAccountController::class, 'activate'])->name('mailgun-accounts.activate');
    Route::delete('settings/mailgun-accounts/{account}', [MailgunAccountController::class, 'destroy'])->name('mailgun-accounts.destroy');
});

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');
});
