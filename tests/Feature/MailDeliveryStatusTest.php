<?php

namespace Tests\Feature;

use App\Jobs\SendMailMessageJob;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use App\Notifications\MailNotDeliveredNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MailDeliveryStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
    }

    private function message(string $status = 'sent', string $direction = 'outbound'): MailMessage
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Proposal']);

        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => $direction,
            'folder' => $direction === 'outbound' ? 'sent' : 'inbox',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'recipient@example.com']],
            'subject' => 'Proposal',
            'message_id' => '<proposal@example.com>',
            'mailgun_message_id' => 'proposal@example.com',
            'status' => $status,
        ]);
    }

    /**
     * @param  array<string, mixed>  $eventData
     */
    private function mailgunEvent(string $event, array $eventData = []): void
    {
        $timestamp = (string) now()->timestamp;
        $token = Str::random(20);

        $this
            ->postJson('/api/webhooks/mailgun/events', [
                'signature' => [
                    'timestamp' => $timestamp,
                    'token' => $token,
                    'signature' => hash_hmac('sha256', $timestamp.$token, 'webhook-secret'),
                ],
                'event-data' => [
                    'id' => Str::uuid()->toString(),
                    'event' => $event,
                    'message' => ['headers' => ['message-id' => 'proposal@example.com']],
                    ...$eventData,
                ],
            ])
            ->assertOk();
    }

    public function test_an_open_by_gmails_image_fetcher_isnt_a_read(): void
    {
        $message = $this->message('delivered');

        $this->mailgunEvent('opened', ['client-info' => ['bot' => 'gmail', 'client-name' => 'Edge']]);
        $this->assertSame('delivered', $message->refresh()->status);

        $this->mailgunEvent('opened', ['client-info' => ['bot' => null, 'client-name' => 'Chrome Mobile']]);
        $this->assertSame('opened', $message->refresh()->status);
    }

    public function test_events_arriving_late_never_move_a_message_back(): void
    {
        $message = $this->message('opened');

        $this->mailgunEvent('delivered');
        $this->mailgunEvent('accepted');
        $this->mailgunEvent('failed', ['severity' => 'temporary']);

        $this->assertSame('opened', $message->refresh()->status);
    }

    public function test_a_bounce_stays_visible_when_another_recipient_opens_the_message(): void
    {
        $message = $this->message();

        $this->mailgunEvent('failed', ['severity' => 'permanent']);
        $this->mailgunEvent('opened');

        $this->assertSame('permanent_failed', $message->refresh()->status);
    }

    public function test_events_for_mail_coming_in_leave_it_received(): void
    {
        $message = $this->message('received', 'inbound');

        $this->mailgunEvent('accepted');

        $this->assertSame('received', $message->refresh()->status);
    }

    public function test_the_sender_is_told_once_when_a_message_bounces(): void
    {
        Notification::fake();
        $message = $this->message();

        $this->mailgunEvent('failed', [
            'severity' => 'permanent',
            'delivery-status' => ['message' => '550 5.1.1 The email account does not exist'],
        ]);
        $this->mailgunEvent('failed', ['severity' => 'permanent']);

        $this->assertSame('550 5.1.1 The email account does not exist', $message->refresh()->error_message);
        Notification::assertSentToTimes($message->user, MailNotDeliveredNotification::class, 1);
        Notification::assertSentTo($message->user, MailNotDeliveredNotification::class, function (MailNotDeliveredNotification $notification) use ($message): bool {
            $data = $notification->toArray($message->user);

            return $data['url'] === "/mail/sent/{$message->mail_thread_id}"
                && str_contains($data['message'], 'recipient@example.com');
        });
    }

    public function test_the_sender_isnt_told_about_a_temporary_failure_mailgun_will_retry(): void
    {
        Notification::fake();
        $message = $this->message();

        $this->mailgunEvent('failed', ['severity' => 'temporary']);

        $this->assertSame('temporary_failed', $message->refresh()->status);
        Notification::assertNothingSent();
    }

    public function test_the_sender_is_told_when_a_message_couldnt_be_sent_at_all(): void
    {
        Notification::fake();
        $message = $this->message('queued');

        (new SendMailMessageJob($message->id))->failed(new RuntimeException('Mailgun refused the request'));

        $this->assertSame('failed', $message->refresh()->status);
        Notification::assertSentTo($message->user, MailNotDeliveredNotification::class);
    }
}
