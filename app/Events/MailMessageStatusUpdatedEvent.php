<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailMessageStatusUpdatedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public string $userId,
        public string $messageId,
        public string $threadId,
        public string $status,
        public ?string $errorMessage,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
    * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mail.'.$this->userId),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->messageId,
            'threadId' => $this->threadId,
            'status' => $this->status,
            'errorMessage' => $this->errorMessage,
        ];
    }
}
