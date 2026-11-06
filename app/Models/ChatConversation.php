<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatConversation extends Model
{
    use HasFactory, HasUuids;

    protected $guarded = [];

    protected $casts = [
        'last_message_at' => 'datetime',
    ];

    // Microsecond precision: last_message_at is compared against a
    // participant's delete cutoff (scopeNotDeletedBy below) — the default
    // second-precision format can make two events milliseconds apart
    // compare as equal instead of ordered.
    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'chat_conversation_participants', 'conversation_id', 'user_id')
            ->using(ChatConversationParticipant::class)
            ->withPivot(
                'last_read_at',
                'archived_at',
                'deleted_at',
                'is_sharing_location',
                'location_latitude',
                'location_longitude',
                'location_accuracy_meters',
                'location_updated_at',
            )
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function scopeForUser(Builder $query, string $userId): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('users.id', $userId));
    }

    public function scopeNotArchivedBy(Builder $query, string $userId): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $q) => $q->where('users.id', $userId)
                ->whereNull('chat_conversation_participants.archived_at')
        );
    }

    public function scopeArchivedBy(Builder $query, string $userId): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $q) => $q->where('users.id', $userId)
                ->whereNotNull('chat_conversation_participants.archived_at')
        );
    }

    // "Deleting" a conversation sets a per-participant cutoff (deleted_at)
    // rather than a boolean hide: it clears everything up to that point from
    // that user's view, but isn't a dead end — once a message lands after the
    // cutoff (from either side), the conversation reappears for them with
    // that message as the start of their visible history. See
    // ChatConversationService::show() for the matching message-list cutoff.
    public function scopeNotDeletedBy(Builder $query, string $userId): Builder
    {
        return $query->whereHas(
            'participants',
            fn (Builder $q) => $q->where('users.id', $userId)
                ->where(function (Builder $q2) {
                    $q2->whereNull('chat_conversation_participants.deleted_at')
                        ->orWhereColumn(
                            'chat_conversations.last_message_at',
                            '>',
                            'chat_conversation_participants.deleted_at'
                        );
                })
        );
    }
}
