<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MailgunCredentialsController extends Controller
{
    /**
     * Update the user's own Mailgun credentials.
     */
    /**
     * Remove the user's own Mailgun credentials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->session()->flash('flash.toast', ['type' => 'success', 'message' => __('Mailgun credentials are managed as mail accounts.')]);

        return to_route('profile.edit');
    }
}
