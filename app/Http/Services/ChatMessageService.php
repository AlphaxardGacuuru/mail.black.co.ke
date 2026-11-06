<?php

namespace App\Http\Services;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatMessageStar;
use App\Models\TemporaryUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ChatMessageService extends Service
{
    public function send(
        string $conversationId,
        ?string $body,
        array $temporaryUploadIds = [],
        ?string $replyToId = null
    ): array {
        $conversation = ChatConversation::forUser($this->id)->findOrFail($conversationId);

        if (blank($body) && empty($temporaryUploadIds)) {
            throw ValidationException::withMessages([
                'body' => ['A message needs text or an attachment.'],
            ]);
        }

        $replyTo = $replyToId
            ? ChatMessage::where('conversation_id', $conversation->id)->findOrFail($replyToId)
            : null;

        $message = new ChatMessage;
        $message->conversation_id = $conversation->id;
        $message->sender_id = $this->id;
        $message->body = $body;
        $message->reply_to_id = $replyTo?->id;
        $saved = $message->save();

        if (! $saved) {
            return [false, 'Message Could Not Be Sent', null];
        }

        $this->attachTemporaryUploads($message, $temporaryUploadIds);

        $this->finalizeNewMessage($conversation, $message);

        return [true, 'Message Sent', $message];
    }

    public function destroy(string $id): array
    {
        $message = ChatMessage::where('sender_id', $this->id)->findOrFail($id);
        $deleted = $message->delete();

        return [$deleted, 'Message Deleted'];
    }

    public function toggleStar(string $id): array
    {
        $message = ChatMessage::whereHas(
            'conversation',
            fn ($query) => $query->forUser($this->id)
        )->findOrFail($id);

        $star = ChatMessageStar::where('message_id', $message->id)
            ->where('user_id', $this->id)
            ->first();

        if ($star) {
            $star->delete();

            return [true, 'Message Unstarred', false];
        }

        ChatMessageStar::create(['message_id' => $message->id, 'user_id' => $this->id]);

        return [true, 'Message Starred', true];
    }

    public function forward(string $id, string $targetConversationId): array
    {
        $source = ChatMessage::whereHas(
            'conversation',
            fn ($query) => $query->forUser($this->id)
        )->with('attachments')->findOrFail($id);

        $targetConversation = ChatConversation::forUser($this->id)->findOrFail($targetConversationId);

        if (blank($source->body) && $source->attachments->isEmpty()) {
            throw ValidationException::withMessages([
                'message' => ['This message can no longer be forwarded.'],
            ]);
        }

        $forwarded = new ChatMessage;
        $forwarded->conversation_id = $targetConversation->id;
        $forwarded->sender_id = $this->id;
        $forwarded->body = $source->body;
        $forwarded->save();

        foreach ($source->attachments as $attachment) {
            $copy = new ChatMessageAttachment;
            $copy->message_id = $forwarded->id;
            $copy->disk = $attachment->disk;
            $copy->path = $attachment->path;
            $copy->original_name = $attachment->original_name;
            $copy->mime_type = $attachment->mime_type;
            $copy->size = $attachment->size;
            $copy->save();
        }

        $this->finalizeNewMessage($targetConversation, $forwarded);

        return [true, 'Message Forwarded', $forwarded];
    }

    protected function finalizeNewMessage(ChatConversation $conversation, ChatMessage $message): void
    {
        $conversation->update(['last_message_at' => $message->created_at]);

        $message->load(['sender', 'attachments', 'replyTo.attachments']);
    }

    protected function attachTemporaryUploads(ChatMessage $message, array $temporaryUploadIds): void
    {
        $ids = collect($temporaryUploadIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $temporaryUploads = TemporaryUpload::whereIn('id', $ids)->get();

        foreach ($temporaryUploads as $temporaryUpload) {
            $disk = $temporaryUpload->disk ?: 'public';
            $finalPath = "chat-attachments/{$message->id}/" . basename($temporaryUpload->path);

            Storage::disk($disk)->move($temporaryUpload->path, $finalPath);

            $attachment = new ChatMessageAttachment;
            $attachment->message_id = $message->id;
            $attachment->disk = $disk;
            $attachment->path = $finalPath;
            $attachment->original_name = $temporaryUpload->original_name;
            $attachment->mime_type = $temporaryUpload->mime_type;
            $attachment->size = $temporaryUpload->size;
            $attachment->save();
        }

        TemporaryUpload::whereIn('id', $temporaryUploads->pluck('id'))->delete();
    }
}
