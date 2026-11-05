<?php

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('app'))->name('home');

require __DIR__ . '/auth.php';

require __DIR__ . '/settings.php';

// Posts shared to the installed app are caught by the service worker; this
// only runs before it's active, and opens the page to paste the post into.
Route::post('share-target', fn() => redirect('/job-applications/add'))
    ->withoutMiddleware(PreventRequestForgery::class);

Route::fallback(fn() => view('app'));
