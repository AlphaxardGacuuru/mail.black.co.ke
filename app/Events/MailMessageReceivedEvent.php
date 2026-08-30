<?php

namespace App\Events;

use App\Models\MailMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MailMessageReceivedEvent implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public MailMessage $mailMessage,
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
    * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('mail.'.$this->mailMessage->user_id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'messageId' => $this->mailMessage->id,
            'threadId' => $this->mailMessage->mail_thread_id,
        ];
    }
}
