<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\PasswordUpdateRequest;
use App\Support\Spa;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SecurityController extends Controller
{
    public function __construct()
    {
        $this->middleware('password.confirm')->only('edit');
    }

    /**
     * Show the user's security settings page.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        $props = [
            'canManageTwoFactor' => true,
            'twoFactorEnabled' => $user?->hasTwoFactorEnabled() ?? false,
            'requiresConfirmation' => ! ($user?->hasTwoFactorEnabled() ?? false),
        ];

        return Spa::render('settings/security', $props);
    }

    /**
     * Update the user's password.
     */
    public function update(PasswordUpdateRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->password,
        ]);

        $request->session()->flash('flash.toast', ['type' => 'success', 'message' => __('Password updated.')]);

        return back();
    }
}
