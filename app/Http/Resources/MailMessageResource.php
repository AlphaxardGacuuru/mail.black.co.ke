<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailMessageResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'threadId' => $this->mail_thread_id,
            'direction' => $this->direction,
            'folder' => $this->folder,
            'from' => $this->from_address,
            'to' => $this->to ?? [],
            'cc' => $this->cc ?? [],
            'bcc' => $this->bcc ?? [],
            'subject' => $this->subject,
            'bodyHtml' => $this->body_html,
            'bodyText' => $this->body_text,
            'snippet' => $this->snippet,
            'status' => $this->status,
            'errorMessage' => $this->error_message,
            'isRead' => $this->is_read,
            'isStarred' => $this->is_starred,
            'hasAttachments' => $this->has_attachments,
            'attachments' => MailAttachmentResource::collection($this->whenLoaded('attachments')),
            'labels' => MailLabelResource::collection($this->whenLoaded('labels')),
            'sentAt' => $this->sent_at,
            'receivedAt' => $this->received_at,
            'createdAt' => $this->created_at,
        ];
    }
}
