<?php

namespace Tests\Feature;

use App\Http\Services\MailMessageService;
use App\Http\Services\MailSanitizerService;
use App\Models\MailgunAccount;
use App\Models\MailMessage;
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
