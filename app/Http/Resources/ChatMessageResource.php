<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversationId' => $this->conversation_id,
            'senderId' => $this->sender_id,
            'body' => $this->body,
            'attachments' => ChatMessageAttachmentResource::collection($this->whenLoaded('attachments')),
            'isRead' => (bool) ($this->is_read_by_recipient ?? false),
            'isStarred' => (bool) ($this->is_starred_by_viewer ?? false),
            'replyTo' => $this->whenLoaded('replyTo', fn () => $this->replyTo ? [
                'id' => $this->replyTo->id,
                'senderId' => $this->replyTo->sender_id,
                'body' => $this->replyTo->body,
                'hasAttachments' => $this->replyTo->attachments->isNotEmpty(),
            ] : null),
            'createdAt' => $this->created_at,
        ];
    }
}
