<?php

namespace App\Events;

use App\Models\ChatConversation;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued for the same reason as ChatMessageSentEvent: the recipient must reliably
// see a location icon appear, so this can't depend on the broadcaster being
// reachable at the exact moment the share starts.
class LocationShareStartedEvent implements ShouldBroadcast
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
