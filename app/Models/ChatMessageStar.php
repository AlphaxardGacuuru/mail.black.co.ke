<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessageStar extends Model
{
    protected $guarded = [];

    public function message(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'message_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
