<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued for the same reason as ChatMessageSentEvent: marking a conversation read
// must succeed even if the realtime broadcaster is unreachable.
class ChatConversationReadEvent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public ChatConversation $conversation, public string $readByUserId) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat-conversation.' . $this->conversation->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversationId' => $this->conversation->id,
            'readByUserId' => $this->readByUserId,
            'readAt' => now()->toIso8601String(),
        ];
    }
}
