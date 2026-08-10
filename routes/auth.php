<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function() {
    Route::get('register', fn() => view('app'))->name('register');

    Route::post('register', [RegisteredUserController::class, 'store'])
        ->name('register.store');

    Route::get('login', fn() => view('app'))->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');

    Route::get('forgot-password', fn() => view('app'))
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', fn() => view('app'))
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.update');
});

Route::get('two-factor-challenge', fn() => view('app'))
    ->name('two-factor.login');

Route::post('two-factor-challenge', [TwoFactorChallengeController::class, 'store']);

Route::get('verify-email/{id}/{hash}', [EmailVerificationPromptController::class, '__invoke'])
    ->name('verification.notice');

Route::middleware('auth:sanctum')->group(function() {
    Route::post('verify-email/{id}/{hash}', [VerifyEmailController::class, '__invoke'])
        // ->middleware(['signed', 'throttle:6,1'])
        ->middleware(['throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', fn() => view('app'))
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::post('two-factor/enable', [TwoFactorController::class, 'enable'])
        ->name('two-factor.enable');

    Route::post('two-factor/confirm', [TwoFactorController::class, 'confirm'])
        ->name('two-factor.confirm');

    Route::post('two-factor/disable', [TwoFactorController::class, 'disable'])
        ->name('two-factor.disable');

    Route::get('two-factor/qr-code', [TwoFactorController::class, 'qrCode'])
        ->name('two-factor.qr-code');

    Route::get('two-factor/secret-key', [TwoFactorController::class, 'secretKey'])
        ->name('two-factor.secret-key');

    Route::get('two-factor/recovery-codes', [TwoFactorController::class, 'recoveryCodes'])
        ->name('two-factor.recovery-codes');

    Route::post('two-factor/recovery-codes', [TwoFactorController::class, 'regenerateRecoveryCodes'])
        ->name('two-factor.recovery-codes.regenerate');
});

/*
 * Social logins */
Route::get('login/{website}/redirect', [AuthenticatedSessionController::class, 'redirectToProvider'])
    ->middleware('guest');

Route::get('login/{website}/callback', [AuthenticatedSessionController::class, 'handleProviderCallback']);
