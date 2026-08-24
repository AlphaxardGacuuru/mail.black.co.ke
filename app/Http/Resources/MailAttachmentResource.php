<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailAttachmentResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'filename' => $this->original_name,
            'mimeType' => $this->mime_type,
            'size' => $this->size,
            'isInline' => $this->is_inline,
            'downloadUrl' => route('mail.attachments.download', $this->id),
        ];
    }
}
