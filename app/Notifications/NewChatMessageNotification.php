<?php

namespace App\Notifications;

use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Notifications\Concerns\RespectsNotificationPreferences;
use App\Notifications\Messages\WebPushMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessageNotification extends Notification implements ShouldQueue
{
    use Queueable, RespectsNotificationPreferences;

    public function __construct(protected ChatMessage $message) {}

    /**
     * The config/notifications.php category, whose channels the user chooses.
     */
    protected function category(): string
    {
        return 'new_chat_message';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'url' => "/chats/{$this->message->conversation_id}/show",
            'from' => $this->message->sender->name,
            'message' => $this->body(),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("New Message From {$this->message->sender->name}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$this->message->sender->name} sent you a message:")
            ->line($this->body())
            ->action('Open Chat', url("/chats/{$this->message->conversation_id}/show"));
    }

    public function toWebPush($notifiable, $notification): WebPushMessage
    {
        $conversationId = $this->message->conversation_id;

        return (new WebPushMessage)
            ->title($this->message->sender->name)
            ->icon($this->message->sender->avatar ?? '/notification-badge-192x192.png')
            // The small badge shown in the OS notification tray/status bar —
            // always the app's own mark, regardless of who sent the message.
            // A transparent monochrome PNG (not android-chrome-192x192.png,
            // which has an opaque background) so Android renders a clean
            // silhouette instead of a solid colored square.
            ->badge('/notification-badge-192x192.png')
            ->body($this->body())
            // Group notifications per conversation so a burst of messages
            // replaces the previous banner instead of stacking indefinitely,
            // while renotify still re-alerts the user for each one.
            ->tag('chat-conversation-' . $conversationId)
            ->renotify()
            // Handled in resources/js/sw.ts's notificationclick listener.
            // Browsers that support notification actions show at most 2.
            ->openAction('reply', 'Reply', "/chats/{$conversationId}/show")
            ->signedAction('mark-read', 'Mark As Read', 'push-actions.chat.conversations.read', [
                'user' => $notifiable->id,
                'conversation' => $conversationId,
            ])
            ->data(['url' => "/chats/{$conversationId}/show"]);
    }

    protected function body(): string
    {
        if (filled($this->message->body)) {
            return Str::limit($this->message->body, 120);
        }

        $attachmentCount = $this->message->attachments->count();

        return $attachmentCount > 1
            ? "Sent {$attachmentCount} attachments"
            : 'Sent an attachment';
    }
}
