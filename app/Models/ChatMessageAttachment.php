<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageAttachment extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'size' => 'integer',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }
}
