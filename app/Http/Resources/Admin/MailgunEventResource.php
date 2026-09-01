<?php

namespace App\Http\Resources\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailgunEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'providerEventId' => $this->provider_event_id,
            'event' => $this->event,
            'status' => $this->status,
            'mailMessageId' => $this->mail_message_id,
            'mailMessageSubject' => $this->mailMessage?->subject,
            'occurredAt' => $this->occurred_at,
            'createdAt' => $this->created_at,
            'payload' => $this->payload,
        ];
    }
}
