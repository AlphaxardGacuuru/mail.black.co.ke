<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Services\MailgunMailboxService;
use App\Models\MailgunAccount;
use Illuminate\Http\JsonResponse;

/**
 * An admin removing anyone's mailbox, here and on Mailgun.
 */
class AdminMailgunAccountController extends Controller
{
    public function __construct(protected MailgunMailboxService $mailboxService)
    {
        //
    }

    public function destroy(string $id): JsonResponse
    {
        $this->mailboxService->removeAccount(MailgunAccount::query()
            ->findOrFail($id));

        return response()->json(['message' => 'Mailbox Removed']);
    }
}
