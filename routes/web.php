<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn() => view('app'))->name('home');

require __DIR__ . '/auth.php';

require __DIR__ . '/settings.php';

Route::fallback(fn() => view('app'));
