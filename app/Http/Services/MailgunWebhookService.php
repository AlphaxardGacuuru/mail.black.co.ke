<?php

namespace App\Http\Services;

use App\Enums\MailStatus;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Models\MailgunEvent;
use App\Models\MailMessage;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MailgunWebhookService
{
    /**
     * How far a message has got on its way: an event never moves it back,
     * as Mailgun's events can arrive out of order.
     */
    private const PROGRESS = [
        MailStatus::QUEUED->value => 0,
        MailStatus::SENT->value => 1,
        MailStatus::DELIVERED->value => 2,
        MailStatus::OPENED->value => 3,
        MailStatus::CLICKED->value => 4,
    ];

    /**
     * The message didn't reach a recipient. Kept even when another
     * recipient later opens it, so the failure isn't hidden.
     */
    private const UNDELIVERED = [
        MailStatus::FAILED->value,
        MailStatus::BOUNCED->value,
        MailStatus::PERMANENT_FAILED->value,
    ];

    /**
     * Process an account-level Mailgun event webhook.
     *
     * @return array{status: bool, message: string, updated: int}
     */
    public function handleEvent(Request $request): array
    {
        $payload = $request->all();
        $event = (string) (data_get($payload, 'event-data.event') ?? data_get($payload, 'event', ''));
        $status = $this->statusFor($event, $payload);
        $messageId = $this->messageId($payload);
        $providerEventId = (string) (data_get($payload, 'event-data.id') ?? data_get($payload, 'id', ''));

        if ($providerEventId === '') {
            return [
                'status' => true,
                'message' => 'Event ignored',
                'updated' => 0
            ];
        }

        if (MailgunEvent::where('provider_event_id', $providerEventId)->exists()) {
            return [
                'status' => true,
                'message' => 'Event already processed',
                'updated' => 0
            ];
        }

        // Mail coming in has events too ("accepted" when Mailgun takes it):
        // only mail the user sent has a delivery status.
        $message = $messageId === '' ? null : MailMessage::query()
            ->where('direction', 'outbound')
            ->where(fn ($query) => $query
                ->where('mailgun_message_id', $messageId)
                ->orWhere('message_id', $messageId)
                ->orWhere('message_id', '<' . $messageId . '>'))
            ->first();

        $mailgunEvent = new MailgunEvent;
        $mailgunEvent->provider_event_id = $providerEventId;
        $mailgunEvent->mail_message_id = $message?->id;
        $mailgunEvent->event = $event;
        $mailgunEvent->status = $status;
        $mailgunEvent->occurred_at = $this->occurredAt($payload);
        $mailgunEvent->payload = $payload;
        $mailgunEvent->save();

        if (! $status || $messageId === '') {
            return [
                'status' => true,
                'message' => 'Event ignored',
                'updated' => 0
            ];
        }

        if (! $message) {
            return [
                'status' => true,
                'message' => 'Message Not Found',
                'updated' => 0
            ];
        }

        if (! $this->moves($message->status, $status)) {
            return [
                'status' => true,
                'message' => 'Event ignored',
                'updated' => 0
            ];
        }

        $errorMessage = data_get($payload, 'event-data.delivery-status.message')
            ?? data_get($payload, 'delivery-status.message')
            ?? data_get($payload, 'event-data.reason')
            ?? data_get($payload, 'reason');

        $message->status = $status;

        if ($errorMessage !== null) {
            $message->error_message = $errorMessage;
        }

        $message->save();

        MailMessageStatusUpdatedEvent::dispatch(
            $message->user_id,
            $message->id,
            $message->mail_thread_id,
            $status,
            $errorMessage,
        );

        return [
            'status' => true,
            'message' => 'Event processed',
            'updated' => 1
        ];
    }

    /**
     * Whether an event's status replaces the message's current one: not
     * when it's the same, a step back, a temporary failure after delivery,
     * or progress after the message failed to reach someone.
     */
    private function moves(?string $current, string $status): bool
    {
        if ($current === $status) {
            return false;
        }

        if (in_array($current, self::UNDELIVERED, true) && isset(self::PROGRESS[$status])) {
            return false;
        }

        if ($status === MailStatus::TEMPORARY_FAILED->value) {
            return (self::PROGRESS[$current] ?? 0) < self::PROGRESS[MailStatus::DELIVERED->value];
        }

        return ! isset(self::PROGRESS[$current], self::PROGRESS[$status])
            || self::PROGRESS[$status] > self::PROGRESS[$current];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function messageId(array $payload): string
    {
        $messageId = data_get($payload, 'event-data.message.headers.message-id')
            ?? data_get($payload, 'message.headers.message-id')
            ?? data_get($payload, 'event-data.message-id')
            ?? data_get($payload, 'message-id');

        return trim((string) $messageId, " <>\t\n\r\0\x0B");
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function occurredAt(array $payload): ?Carbon
    {
        $timestamp = data_get($payload, 'event-data.timestamp') ?? data_get($payload, 'timestamp');

        return is_numeric($timestamp) ? Carbon::createFromTimestamp((int) $timestamp) : null;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function statusFor(string $event, array $payload): ?string
    {
        return match ($event) {
            'accepted' => MailStatus::SENT->value,
            'delivered' => MailStatus::DELIVERED->value,
            // Gmail and Apple Mail fetch images and links themselves the
            // moment a message arrives, before anyone reads it; Mailgun
            // names the bot, and those aren't the recipient opening it.
            'opened' => $this->byBot($payload) ? null : MailStatus::OPENED->value,
            'clicked' => $this->byBot($payload) ? null : MailStatus::CLICKED->value,
            'complained', 'spam_complaint' => MailStatus::COMPLAINED->value,
            'unsubscribed' => MailStatus::UNSUBSCRIBED->value,
            'permanent_failure', 'permanent-failure' => MailStatus::PERMANENT_FAILED->value,
            'temporary_failure', 'temporary-failure' => MailStatus::TEMPORARY_FAILED->value,
            'failed' => $this->failureStatus($payload),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function byBot(array $payload): bool
    {
        return filled(data_get($payload, 'event-data.client-info.bot') ?? data_get($payload, 'client-info.bot'));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function failureStatus(array $payload): string
    {
        $severity = data_get($payload, 'event-data.severity')
            ?? data_get($payload, 'severity');

        return $severity === 'permanent'
            ? MailStatus::PERMANENT_FAILED->value
            : MailStatus::TEMPORARY_FAILED->value;
    }
}
