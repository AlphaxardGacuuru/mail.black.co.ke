<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailgunCredentialsUpdateRequest;
use App\Notifications\MailgunCredentialsUpdatedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MailgunCredentialsController extends Controller
{
    /**
     * Update the user's own Mailgun credentials.
     */
    public function update(MailgunCredentialsUpdateRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        if (blank($data['mailgun_api_key'] ?? null)) {
            unset($data['mailgun_api_key']);
        }

        $user->fill($data);

        $credentialsChanged = $user->isDirty([
            'mailgun_domain',
            'mailgun_api_key',
            'mailgun_endpoint'
        ]);

        $user->save();

        if ($credentialsChanged) {
            $user->notify(new MailgunCredentialsUpdatedNotification);
        }

        $request
            ->session()
            ->flash('flash.toast', [
                'type' => 'success',
                'message' => __('Mailgun Credentials Updated.')
            ]);

        return response()->json([
            'message' => __('Mailgun Credentials Updated.'),
            'redirect' => route('profile.edit')
        ], 200);
    }

    /**
     * Remove the user's own Mailgun credentials.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $hadCredentials = $user->hasMailgunCredentials();

        $user->forceFill([
            'mailgun_domain' => null,
            'mailgun_api_key' => null,
            'mailgun_endpoint' => null,
        ])->save();

        if ($hadCredentials) {
            $user->notify(new MailgunCredentialsUpdatedNotification(removed: true));
        }

        $request->session()->flash('flash.toast', ['type' => 'success', 'message' => __('Mailgun credentials removed.')]);

        return to_route('profile.edit');
    }
}
