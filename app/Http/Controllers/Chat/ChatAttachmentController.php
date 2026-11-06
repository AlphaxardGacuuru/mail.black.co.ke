<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatMessageAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatAttachmentController extends Controller
{
    public function download(string $id): StreamedResponse
    {
        $userId = auth('sanctum')->id();

        $attachment = ChatMessageAttachment::whereHas(
            'message.conversation.participants',
            fn ($query) => $query->where('users.id', $userId)
        )->findOrFail($id);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name ?? basename($attachment->path)
        );
    }
}
