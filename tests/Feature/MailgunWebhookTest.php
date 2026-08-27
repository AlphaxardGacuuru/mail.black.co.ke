<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailgunWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_mailgun_event_updates_message_status(): void
    {
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
                'event' => 'delivered',
                'message' => ['headers' => ['message-id' => '<mailgun-message@example.com>']],
            ],
        ]);

        $response->assertOk()->assertJson(['status' => true, 'updated' => 1]);
        $this->assertSame('delivered', $message->refresh()->status);
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
                'event' => 'failed',
                'severity' => 'permanent',
                'message' => ['headers' => ['message-id' => 'failure-message@example.com']],
            ],
        ])->assertOk();

        $this->assertSame('permanent_failed', $message->refresh()->status);
    }
}
