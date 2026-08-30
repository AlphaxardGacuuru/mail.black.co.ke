<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Models\MailgunEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use App\Events\MailMessageStatusUpdatedEvent;
use App\Events\MailMessageReceivedEvent;
use App\Models\MailgunAccount;
use Tests\TestCase;

class MailgunWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailgun_event_updates_message_status(): void
    {
        Event::fake([MailMessageStatusUpdatedEvent::class]);
        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Status update']);
        $message = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'recipient@example.com']],
            'subject' => 'Status update',
            'message_id' => '<mailgun-message@example.com>',
            'mailgun_message_id' => 'mailgun-message@example.com',
            'status' => 'sent',
        ]);

        $timestamp = (string) now()->timestamp;
        $token = 'unique-webhook-token';
        $response = $this->postJson('/api/webhooks/mailgun/events', [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => $token,
                'signature' => hash_hmac('sha256', $timestamp . $token, 'webhook-secret'),
            ],
            'event-data' => [
                'id' => 'delivered-event-id',
                'event' => 'delivered',
                'timestamp' => 1_521_472_262.908181,
                'message' => ['headers' => ['message-id' => '<mailgun-message@example.com>']],
            ],
        ]);

        $response->assertOk()->assertJson(['status' => true, 'updated' => 1]);
        $this->assertSame('delivered', $message->refresh()->status);
        $this->assertDatabaseHas('mailgun_events', [
            'provider_event_id' => 'delivered-event-id',
            'mail_message_id' => $message->id,
            'event' => 'delivered',
            'status' => 'delivered',
        ]);
        Event::assertDispatched(MailMessageStatusUpdatedEvent::class, function (MailMessageStatusUpdatedEvent $event) use ($message): bool {
            return $event->userId === $message->user_id
                && $event->messageId === $message->id
                && $event->threadId === $message->mail_thread_id
                && $event->status === 'delivered';
        });
    }

    public function test_mailgun_failure_severity_is_preserved_in_status(): void
    {
        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Failure update']);
        $message = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'recipient@example.com']],
            'subject' => 'Failure update',
            'mailgun_message_id' => 'failure-message@example.com',
            'status' => 'sent',
        ]);

        $timestamp = (string) now()->timestamp;
        $token = 'another-webhook-token';
        $this->postJson('/api/webhooks/mailgun/events', [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp . $token, 'webhook-secret'),
            'event-data' => [
                'id' => 'permanent-failure-event-id',
                'event' => 'failed',
                'severity' => 'permanent',
                'message' => ['headers' => ['message-id' => 'failure-message@example.com']],
            ],
        ])->assertOk();

        $this->assertSame('permanent_failed', $message->refresh()->status);
    }

    public function test_duplicate_provider_event_is_stored_once(): void
    {
        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
        $timestamp = (string) now()->timestamp;

        MailgunEvent::create([
            'provider_event_id' => 'duplicate-event-id',
            'event' => 'delivered',
            'status' => 'delivered',
            'payload' => [],
        ]);

        $response = $this->postJson('/api/webhooks/mailgun/events', [
            'signature' => [
                'timestamp' => $timestamp,
                'token' => 'retry-webhook-token',
                'signature' => hash_hmac('sha256', $timestamp . 'retry-webhook-token', 'webhook-secret'),
            ],
            'event-data' => [
                'id' => 'duplicate-event-id',
                'event' => 'delivered',
            ],
        ]);

        $response->assertOk()->assertJson(['updated' => 0, 'message' => 'Event already processed']);
        $this->assertSame(1, MailgunEvent::where('provider_event_id', 'duplicate-event-id')->count());
    }

    public function test_mailgun_inbound_webhook_broadcasts_the_new_message(): void
    {
        Event::fake([MailMessageReceivedEvent::class]);
        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
        $user = User::factory()->create();
        MailgunAccount::create([
            'user_id' => $user->id,
            'mailbox_address' => 'inbox@example.com',
            'mailgun_domain' => 'example.com',
            'mailgun_api_key' => 'key',
            'mailgun_endpoint' => 'api.mailgun.net',
        ]);

        $timestamp = (string) now()->timestamp;
        $token = 'inbound-webhook-token';
        $response = $this->postJson('/api/webhooks/mailgun', [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp . $token, 'webhook-secret'),
            'recipient' => 'inbox@example.com',
            'sender' => 'sender@example.net',
            'from' => 'Sender <sender@example.net>',
            'To' => 'inbox@example.com',
            'subject' => 'Incoming mail',
            'body-plain' => 'Hello',
            'Message-Id' => '<inbound-message@example.net>',
        ]);

        $response->assertOk()->assertJson(['status' => true]);
        $message = MailMessage::where('message_id', 'inbound-message@example.net')->firstOrFail();

        Event::assertDispatched(MailMessageReceivedEvent::class, function (MailMessageReceivedEvent $event) use ($message, $user): bool {
            return $event->mailMessage->id === $message->id
                && $event->mailMessage->user_id === $user->id
                && $event->mailMessage->mail_thread_id === $message->mail_thread_id;
        });
    }
}
