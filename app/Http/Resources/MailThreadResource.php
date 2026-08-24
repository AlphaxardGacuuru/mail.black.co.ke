<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailThreadResource extends JsonResource
{
    public function toArray($request)
    {
        $latest = $this->relationLoaded('messages')
            ? $this->messages->sortByDesc('created_at')->first()
            : $this->messages()->orderByDesc('created_at')->first();

        return [
            'id' => $this->id,
            'subject' => $this->subject,
            'snippet' => $latest?->snippet,
            'from' => $latest?->from_address,
            'hasUnread' => (bool) $this->has_unread,
            'isStarred' => (bool) $this->is_starred,
            'messageCount' => $this->message_count,
            'hasAttachments' => (bool) $latest?->has_attachments,
            'status' => $latest?->status,
            'isRead' => (bool) $latest?->is_read,
            'lastMessageAt' => $this->last_message_at,
            'messages' => MailMessageResource::collection($this->whenLoaded('messages')),
        ];
    }
}
