<?php

namespace App\Http\Controllers\Mail;

use App\Http\Controllers\Controller;
use App\Models\MailAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MailAttachmentController extends Controller
{
    public function download(string $id): StreamedResponse
    {
        $userId = auth('sanctum')->id();

        $attachment = MailAttachment::whereHas(
            'message',
            fn ($query) => $query->where('user_id', $userId)
        )->findOrFail($id);

        return Storage::disk($attachment->disk)->download(
            $attachment->path,
            $attachment->original_name ?? basename($attachment->path)
        );
    }
}
