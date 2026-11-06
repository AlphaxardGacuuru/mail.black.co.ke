<?php

use App\Models\ChatConversation;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('dashboard-narration.{userId}.{streamId}', function ($user, $userId, $streamId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('mail.{userId}', function ($user, string $userId) {
    return $user->id === $userId;
});

// A single presence channel per conversation: presence channels can carry
// regular broadcast events (ChatMessageSentEvent, ChatConversationReadEvent) the same
// way a private channel would, plus join/leave and typing whispers for free.
// Returning an array (rather than a boolean) is what makes this a presence
// channel — Echo prefixes the wire name with "presence-" itself, so the
// pattern registered here stays unprefixed.
Broadcast::channel('chat-conversation.{id}', function ($user, string $id) {
    $isParticipant = ChatConversation::whereKey($id)
        ->whereHas('participants', fn ($query) => $query->where('users.id', $user->id))
        ->exists();

    if (! $isParticipant) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'avatar' => $user->avatar,
    ];
});
