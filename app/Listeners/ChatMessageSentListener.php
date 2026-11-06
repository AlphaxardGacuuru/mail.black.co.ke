<?php

namespace App\Listeners;

use App\Events\ChatMessageSentEvent;
use App\Notifications\NewChatMessageNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;

class ChatMessageSentListener implements ShouldQueue
{
    public function handle(ChatMessageSentEvent $event): void
    {
        $message = $event->message;

        $recipients = $message->conversation->participants()
            ->where('users.id', '!=', $message->sender_id)
            ->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new NewChatMessageNotification($message));
        }
    }
}
