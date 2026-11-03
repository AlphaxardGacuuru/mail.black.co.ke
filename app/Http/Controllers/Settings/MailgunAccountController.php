<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\MailgunAccountRequest;
use App\Http\Resources\MailgunAccountResource;
use App\Http\Services\MailgunMailboxService;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class MailgunAccountController extends Controller
{
    public function __construct(protected MailgunMailboxService $mailboxService)
    {
        //
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->accounts($request->user()));
    }

    public function store(MailgunAccountRequest $request): JsonResponse
    {
        $data = $request->validated();

        $domain = MailgunDomain::query()->findOrFail($data['mailgun_domain_id']);
        $data['mailgun_smtp_password'] = $this->mailboxService->createMailbox(
            $domain,
            $data['mailbox_address']
        );

        $account = $request->user()->mailgunAccounts()->create($data);

        if (! $request->user()->active_mailgun_account_id) {
            $request
                ->user()
                ->update(['active_mailgun_account_id' => $account->id]);
        }

        $request->user()->refresh();

        return response()
            ->json([
                'message' => 'Mailgun Account Added.',
                'accounts' => $this->accounts($request->user())
            ]);
    }

    public function update(MailgunAccountRequest $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        // A job search address stays on the domain it was created on.
        $account->update(collect($request->validated())
            ->when($account->isPlatform(), fn ($data) => $data->except(['mailbox_address', 'mailgun_domain_id']))
            ->all());

        $request->user()->refresh();

        return response()
            ->json([
                'message' => 'Mailgun account updated.',
                'accounts' => $this->accounts($request->user())
            ]);
    }

    public function activate(Request $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        $request->user()->update(['active_mailgun_account_id' => $account->id]);

        return response()
            ->json([
                'message' => 'Mailgun account activated.',
                'accounts' => $this->accounts($request->user())
            ]);
    }

    public function destroy(Request $request, MailgunAccount $account): JsonResponse
    {
        abort_unless($account->user_id === $request->user()->id, 404);

        if ($account->isPlatform()) {
            throw ValidationException::withMessages(['account' => ['Your job search address can\'t be removed.']]);
        }

        $wasActive = $request->user()->active_mailgun_account_id === $account->id;
        $account->delete();

        if ($wasActive) {
            $request->user()->update([
                'active_mailgun_account_id' => $request->user()->mailgunAccounts()->value('id'),
            ]);
        }

        return response()
            ->json([
                'message' => 'Mailgun account removed.',
                'accounts' => $this->accounts($request->user())
            ]);
    }

    private function accounts($user): array
    {
        return MailgunAccountResource::collection(
            $user->mailgunAccounts()->get()
        )->resolve();
    }
}
