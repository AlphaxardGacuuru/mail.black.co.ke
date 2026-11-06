<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChatMessageAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->original_name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'downloadUrl' => route('chat.attachments.download', $this->id),
        ];
    }
}
