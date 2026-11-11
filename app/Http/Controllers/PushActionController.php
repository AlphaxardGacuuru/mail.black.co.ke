<?php

namespace App\Http\Controllers;

use App\Enums\MailFolder;
use App\Events\ChatConversationReadEvent;
use App\Http\Services\ChatConversationService;
use App\Http\Services\MailThreadService;
use App\Http\Services\UserService;
use App\Models\ChatConversation;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * The background buttons on web push notifications. The service worker
 * posts to these through the signed links in each push (see
 * App\Notifications\Messages\WebPushMessage::signedAction()), so $user
 * comes from the signed URL, not a token.
 */
class PushActionController extends Controller
{
    public function __construct(
        protected MailThreadService $mailThreadService,
        protected ChatConversationService $chatConversationService,
        protected UserService $userService,
    ) {
        //
    }

    /**
     * "Mark As Read" on new mail.
     */
    public function readMailThread(User $user, MailThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $user->id, 403);

        [$status] = $this->mailThreadService
            ->actingFor($user)
            ->update($thread->id, null, null, true);

        return response()->json([
            'status' => $status,
            'message' => 'Marked As Read',
        ]);
    }

    /**
     * "Archive" on new mail.
     */
    public function archiveMailThread(User $user, MailThread $thread): JsonResponse
    {
        abort_unless($thread->user_id === $user->id, 403);

        [$status] = $this->mailThreadService
            ->actingFor($user)
            ->update($thread->id, MailFolder::ARCHIVE->value, null, null);

        return response()->json([
            'status' => $status,
            'message' => 'Thread Archived',
        ]);
    }

    /**
     * "Mark As Read" on a chat message, which also shows the sender the
     * read receipt, as reading it in the app does.
     */
    public function readChatConversation(User $user, ChatConversation $conversation): JsonResponse
    {
        abort_unless(
            ChatConversation::query()
                ->forUser($user->id)
                ->whereKey($conversation->id)
                ->exists(),
            403
        );

        [$status, $message, $conversation] = $this->chatConversationService
            ->actingFor($user)
            ->markRead($conversation->id);

        ChatConversationReadEvent::dispatchIf($status, $conversation, $user->id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }

    /**
     * "Verify" on a new signup, for the admin.
     */
    public function verifyUser(User $user, User $subject): JsonResponse
    {
        abort_unless($user->email === config('admin.email'), 403);

        [$status, $message] = $this->userService
            ->actingFor($user)
            ->verify($subject->id, true);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }
}
