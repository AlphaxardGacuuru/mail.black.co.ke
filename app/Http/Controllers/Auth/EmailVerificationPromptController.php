<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailVerificationPromptController extends Controller
{
    /**
     * Display the email verification prompt.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        return redirect(config("app.url")."/#/verify-email/{$request->id}/{$request->hash}?expires={$request->expires}&signature={$request->signature}");

        // return $request->user()->hasVerifiedEmail()
        //             ? redirect()->intended(RouteServiceProvider::HOME)
        //             : Inertia::render('Auth/VerifyEmail', ['status' => session('status')]);
    }
}
