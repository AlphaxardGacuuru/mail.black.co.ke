<?php

namespace App\Http\Controllers\Chat;

use App\Events\ChatConversationReadEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatConversationResource;
use App\Http\Resources\ChatMessageResource;
use App\Http\Services\ChatConversationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChatConversationController extends Controller
{
    public function __construct(protected ChatConversationService $service) {}

    /**
     * People to start a chat with. Only what a chat shows of them, since
     * /api/users returns whole accounts.
     */
    public function users(Request $request): JsonResponse
    {
        $users = User::query()
            ->whereKeyNot($request->user()->id)
            ->when($request->filled('name'), fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$request->string('name').'%')
                ->orWhere('email', 'like', '%'.$request->string('name').'%')))
            ->orderBy('name')
            ->limit(min($request->integer('per_page', 20), 50))
            ->get(['id', 'name', 'email', 'avatar', 'verified'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar,
                'verified' => (bool) $user->verified,
            ]);

        return response()->json(['data' => $users]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        [$status, $message, $conversations] = $this->service->index($request->boolean('archived'));

        return ChatConversationResource::collection($conversations)
            ->additional([
                'status' => $status,
                'message' => $message,
            ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->validate($request, ['userId' => 'required|exists:users,id']);

        [$saved, $message, $conversation] = $this->service->startWith($request->input('userId'));

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'data' => $conversation ? ChatConversationResource::make($conversation) : null,
        ]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation, $messages] = $this->service->show(
            $id,
            (int) $request->input('page', 1)
        );

        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => [
                'conversation' => ChatConversationResource::make($conversation),
                'messages' => ChatMessageResource::collection($messages->getCollection()->reverse()->values()),
                'meta' => [
                    'currentPage' => $messages->currentPage(),
                    'lastPage' => $messages->lastPage(),
                    'total' => $messages->total(),
                ],
            ],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        [$status, $message, $conversation] = $this->service->markRead($id);

        ChatConversationReadEvent::dispatchIf($status, $conversation, $request->user()->id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }

    public function archive(string $id): JsonResponse
    {
        [$status, $message, $isArchived] = $this->service->toggleArchive($id);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'isArchived' => $isArchived,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        [$status, $message] = $this->service->hideForMe($id);

        return response()->json([
            'status' => $status,
            'message' => $message,
        ]);
    }
}
