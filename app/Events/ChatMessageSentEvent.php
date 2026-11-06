<?php

namespace App\Events;

use App\Http\Resources\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

// Queued (not ShouldBroadcastNow) so a broadcaster hiccup — e.g. unset/invalid
// Pusher credentials in local dev — fails the queued job instead of the
// message-send request itself; sending a message must never depend on the
// realtime transport being reachable.
class ChatMessageSentEvent implements ShouldBroadcast
{
    use Dispatchable;

    public function __construct(public ChatMessage $message) {}

    /**
     * @return array<int, PresenceChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat-conversation.' . $this->message->conversation_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'message' => (new ChatMessageResource($this->message))->resolve(),
        ];
    }
}
