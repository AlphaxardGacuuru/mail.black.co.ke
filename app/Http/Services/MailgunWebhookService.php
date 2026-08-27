<?php

namespace App\Http\Services;

use App\Enums\MailStatus;
use App\Models\MailMessage;
use Illuminate\Http\Request;

class MailgunWebhookService
{
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

        if (! $status || $messageId === '') {
            return ['status' => true, 'message' => 'Event ignored', 'updated' => 0];
        }

        $message = MailMessage::query()
            ->where('mailgun_message_id', $messageId)
            ->orWhere('message_id', $messageId)
            ->first();

        if (! $message) {
            return ['status' => true, 'message' => 'Message not found', 'updated' => 0];
        }

        $errorMessage = data_get($payload, 'event-data.delivery-status.message')
            ?? data_get($payload, 'delivery-status.message')
            ?? data_get($payload, 'event-data.reason')
            ?? data_get($payload, 'reason');

        $message->update(array_filter([
            'status' => $status,
            'error_message' => $errorMessage,
        ], static fn ($value): bool => $value !== null));

        return ['status' => true, 'message' => 'Event processed', 'updated' => 1];
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
    private function statusFor(string $event, array $payload): ?string
    {
        return match ($event) {
            'accepted' => MailStatus::SENT->value,
            'delivered' => MailStatus::DELIVERED->value,
            'opened' => MailStatus::OPENED->value,
            'clicked' => MailStatus::CLICKED->value,
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
    private function failureStatus(array $payload): string
    {
        $severity = data_get($payload, 'event-data.severity')
            ?? data_get($payload, 'severity');

        return $severity === 'permanent'
            ? MailStatus::PERMANENT_FAILED->value
            : MailStatus::TEMPORARY_FAILED->value;
    }
}
