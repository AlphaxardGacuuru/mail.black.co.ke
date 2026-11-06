<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued for the same reason as ChatMessageSentEvent: a viewer with the map open
// must reliably be told to close it.
class LocationShareStoppedEvent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(
        public ChatConversation $conversation,
        public string $senderId,
    ) {}

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
            'senderId' => $this->senderId,
        ];
    }
}
