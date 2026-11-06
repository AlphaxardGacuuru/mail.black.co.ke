<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChatMessage extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $guarded = [];

    // Microsecond precision: a message's created_at is compared against a
    // participant's delete cutoff (see ChatConversationService) to decide
    // what's visible to them — the default second-precision format can make
    // two events milliseconds apart compare as equal instead of ordered.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class, 'message_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(ChatMessage::class, 'reply_to_id');
    }

    public function stars(): HasMany
    {
        return $this->hasMany(ChatMessageStar::class, 'message_id');
    }
}
