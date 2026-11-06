<?php

namespace App\Http\Controllers\Chat;

use App\Events\ChatMessageSentEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChatMessageResource;
use App\Http\Services\ChatMessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatMessageController extends Controller
{
    public function __construct(protected ChatMessageService $service) {}

    public function store(Request $request, string $conversationId): JsonResponse
    {
        $this->validate($request, [
            'body' => 'nullable|string',
            'temporaryUploadIds' => 'sometimes|array',
            'replyToId' => 'nullable|uuid',
        ]);

        [$saved, $message, $chatMessage] = $this->service->send(
            $conversationId,
            $request->input('body'),
            $request->input('temporaryUploadIds', []),
            $request->input('replyToId')
        );

        ChatMessageSentEvent::dispatchIf($saved, $chatMessage);

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'data' => $chatMessage ? ChatMessageResource::make($chatMessage) : null,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        [$deleted, $message] = $this->service->destroy($id);

        return response()->json([
            'deleted' => $deleted,
            'message' => $message,
        ]);
    }

    public function star(string $id): JsonResponse
    {
        [$status, $message, $isStarred] = $this->service->toggleStar($id);

        return response()->json([
            'status' => $status,
            'message' => $message,
            'isStarred' => $isStarred,
        ]);
    }

    public function forward(Request $request, string $id): JsonResponse
    {
        $this->validate($request, ['conversationId' => 'required|uuid|exists:chat_conversations,id']);

        [$saved, $message, $chatMessage] = $this->service->forward(
            $id,
            $request->input('conversationId')
        );

        ChatMessageSentEvent::dispatchIf($saved, $chatMessage);

        return response()->json([
            'saved' => $saved,
            'message' => $message,
            'data' => $chatMessage ? ChatMessageResource::make($chatMessage) : null,
        ]);
    }
}
