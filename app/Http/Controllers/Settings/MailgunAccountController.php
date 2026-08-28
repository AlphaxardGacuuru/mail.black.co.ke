<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailgunAccountRequest;
use App\Models\MailgunAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MailgunAccountController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->accounts($request->user()));
    }

    public function store(MailgunAccountRequest $request): JsonResponse
    {
        $account = $request->user()->mailgunAccounts()->create($request->validated());

        if (! $request->user()->active_mailgun_account_id) {
            $request->user()->update(['active_mailgun_account_id' => $account->id]);
        }

        $request->user()->refresh();

        return response()->json(['message' => 'Mailgun account added.', 'accounts' => $this->accounts($request->user())]);
    }

    public function update(MailgunAccountRequest $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        $data = $request->validated();

        if (blank($data['mailgun_api_key'] ?? null)) {
            unset($data['mailgun_api_key']);
        }

        $account->update($data);

        $request->user()->refresh();

        return response()->json(['message' => 'Mailgun account updated.', 'accounts' => $this->accounts($request->user())]);
    }

    public function activate(Request $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        $request->user()->update(['active_mailgun_account_id' => $account->id]);

        return response()->json(['message' => 'Mailgun account activated.', 'accounts' => $this->accounts($request->user())]);
    }

    public function destroy(Request $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        $wasActive = $request->user()->active_mailgun_account_id === $account->id;
        $account->delete();

        if ($wasActive) {
            $request->user()->update([
                'active_mailgun_account_id' => $request->user()->mailgunAccounts()->value('id'),
            ]);
        }

        return response()->json(['message' => 'Mailgun account removed.', 'accounts' => $this->accounts($request->user())]);
    }

    private function accounts($user): array
    {
        $activeId = $user->active_mailgun_account_id;

        return $user->mailgunAccounts()->get()->map(fn(MailgunAccount $account) => [
            'id' => $account->id,
            'mailboxAddress' => $account->mailbox_address,
            'mailFromName' => $account->mail_from_name,
            'mailgunDomain' => $account->mailgun_domain,
            'mailgunEndpoint' => $account->mailgun_endpoint,
            'signature' => $account->signature,
            'avatar' => $account->avatar,
            'isActive' => $account->id === $activeId,
        ])->all();
    }
}
