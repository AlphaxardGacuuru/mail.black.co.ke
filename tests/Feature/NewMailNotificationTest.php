<?php

namespace Tests\Feature;

use App\Events\MailMessageReceivedEvent;
use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewMailNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function account(User $user, string $mailbox): MailgunAccount
    {
        $domain = MailgunDomain::firstOrCreate(
            ['user_id' => $user->id, 'domain' => 'example.com'],
            ['api_key' => 'key'],
        );

        return MailgunAccount::create([
            'user_id' => $user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => $mailbox,
        ]);
    }

    private function receive(string $recipient, string $messageId): void
    {
        config(['services.mailgun.webhook_signing_secret' => 'webhook-secret']);
        $timestamp = (string) now()->timestamp;
        $token = 'token-'.$messageId;

        $this->postJson('/api/webhooks/mailgun', [
            'timestamp' => $timestamp,
            'token' => $token,
            'signature' => hash_hmac('sha256', $timestamp.$token, 'webhook-secret'),
            'recipient' => $recipient,
            'sender' => 'sender@example.net',
            'from' => 'Sender <sender@example.net>',
            'To' => $recipient,
            'subject' => 'Hello there',
            'body-plain' => 'Hello',
            'Message-Id' => "<{$messageId}@example.net>",
        ])->assertOk()->assertJson(['status' => true]);
    }

    public function test_mail_to_an_inactive_mailbox_notifies_with_a_link_that_switches_to_it(): void
    {
        $user = User::factory()->create();
        $active = $this->account($user, 'work@example.com');
        $other = $this->account($user, 'personal@example.com');
        $user->update(['active_mailgun_account_id' => $active->id]);

        $this->receive('personal@example.com', 'to-personal');

        $notification = $user->notifications()->sole();

        $this->assertSame('personal@example.com', $notification->data['mailbox']);
        $this->assertStringEndsWith("/show?account={$other->id}", $notification->data['url']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/notifications')
            ->assertOk()
            ->assertJsonPath('data.0.mailbox', 'personal@example.com');
    }

    public function test_the_realtime_event_names_the_receiving_account(): void
    {
        $user = User::factory()->create();
        $active = $this->account($user, 'work@example.com');
        $other = $this->account($user, 'personal@example.com');
        $user->update(['active_mailgun_account_id' => $active->id]);

        $this->receive('personal@example.com', 'to-personal-event');

        $message = MailMessage::where('message_id', 'to-personal-event@example.net')->firstOrFail();

        $this->assertSame($other->id, (new MailMessageReceivedEvent($message))->broadcastWith()['accountId']);
    }

    public function test_mail_to_the_active_mailbox_links_to_that_account(): void
    {
        $user = User::factory()->create();
        $active = $this->account($user, 'work@example.com');
        $user->update(['active_mailgun_account_id' => $active->id]);

        $this->receive('work@example.com', 'to-work');

        $notification = $user->notifications()->sole();

        $this->assertSame('work@example.com', $notification->data['mailbox']);
        $this->assertStringEndsWith("/show?account={$active->id}", $notification->data['url']);
    }
}
