<?php

namespace Tests\Feature;

use App\Enums\MailStatus;
use App\Http\Services\MailMessageService;
use App\Http\Services\MailSanitizerService;
use App\Models\MailgunAccount;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailMessageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_outbound_message_returns_saved_result_and_message(): void
    {
        $user = User::factory()->create();
        $account = MailgunAccount::create([
            'user_id' => $user->id,
            'mailbox_address' => 'sender@example.com',
            'mailgun_domain' => 'example.com',
            'mailgun_api_key' => 'key',
            'mailgun_endpoint' => 'api.mailgun.net',
        ]);
        $user->update(['active_mailgun_account_id' => $account->id]);

        $service = new TestMailMessageService(new MailSanitizerService);

        [$saved, $mailMessage] = $service->createOutboundMessageForTest($user, [
            'to' => [
                ['address' => 'recipient@example.com', 'name' => null],
            ],
            'cc' => [],
            'bcc' => [],
            'subject' => 'Project update',
            'bodyHtml' => '<p>Hello team</p>',
            'inReplyTo' => null,
            'references' => null,
            'participantEmail' => 'recipient@example.com',
        ], []);

        $this->assertTrue($saved);
        $this->assertInstanceOf(MailMessage::class, $mailMessage);
        $this->assertTrue($mailMessage->exists);
        $this->assertDatabaseHas('mail_messages', [
            'id' => $mailMessage->id,
            'user_id' => $user->id,
            'subject' => 'Project update',
        ]);
    }

    public function test_retry_resets_a_failed_outbound_message_to_queued(): void
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Project update']);
        $mailMessage = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'subject' => 'Project update',
            'status' => MailStatus::FAILED->value,
            'error_message' => 'Connection refused',
        ]);

        $this->actingAs($user, 'sanctum');
        $service = new MailMessageService(new MailSanitizerService);

        $retried = $service->retry($mailMessage->id);

        $this->assertSame(MailStatus::QUEUED->value, $retried->status);
        $this->assertNull($retried->error_message);
        $this->assertDatabaseHas('mail_messages', [
            'id' => $mailMessage->id,
            'status' => MailStatus::QUEUED->value,
            'error_message' => null,
        ]);
    }

    public function test_retry_rejects_a_message_that_is_not_in_a_failed_state(): void
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Project update']);
        $mailMessage = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'subject' => 'Project update',
            'status' => MailStatus::SENT->value,
        ]);

        $this->actingAs($user, 'sanctum');
        $service = new MailMessageService(new MailSanitizerService);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->retry($mailMessage->id);
    }

    public function test_retry_rejects_a_message_belonging_to_another_user(): void
    {
        $owner = User::factory()->create();
        $thread = MailThread::create(['user_id' => $owner->id, 'subject' => 'Project update']);
        $mailMessage = MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $owner->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'subject' => 'Project update',
            'status' => MailStatus::FAILED->value,
        ]);

        $intruder = User::factory()->create();
        $this->actingAs($intruder, 'sanctum');
        $service = new MailMessageService(new MailSanitizerService);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $service->retry($mailMessage->id);
    }
}

class TestMailMessageService extends MailMessageService
{
    /**
     * @param  array<string, mixed>  $fields
     * @param  array<int, mixed>  $temporaryUploadIds
     * @return array{0: bool, 1: MailMessage}
     */
    public function createOutboundMessageForTest(User $user, array $fields, array $temporaryUploadIds): array
    {
        return $this->createOutboundMessage($user, $fields, $temporaryUploadIds);
    }
}
