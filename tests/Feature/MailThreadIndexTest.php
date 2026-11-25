<?php

namespace Tests\Feature;

use App\Models\MailgunAccount;
use App\Models\MailgunDomain;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailThreadIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_threads_can_be_filtered_by_read_status(): void
    {
        $user = $this->userWithMailbox();
        $unread = $this->createThread($user, 'Unread thread', hasUnread: true);
        $read = $this->createThread($user, 'Read thread', hasUnread: false);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/threads?folder=inbox&status=unread')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $unread->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/threads?folder=inbox&status=read')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $read->id);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/threads?folder=inbox')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_an_unknown_read_status_is_rejected(): void
    {
        $user = $this->userWithMailbox();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/threads?folder=inbox&status=archived')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_threads_are_paginated_for_load_more(): void
    {
        $user = $this->userWithMailbox();

        collect(range(1, 21))
            ->each(fn (int $number) => $this->createThread($user, "Thread {$number}", hasUnread: false));

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/threads?folder=inbox&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2);
    }

    private function userWithMailbox(): User
    {
        $user = User::factory()->create();
        $domain = MailgunDomain::create([
            'user_id' => $user->id,
            'domain' => 'example.com',
            'api_key' => 'key',
        ]);
        $account = MailgunAccount::create([
            'user_id' => $user->id,
            'mailgun_domain_id' => $domain->id,
            'mailbox_address' => 'me@example.com',
        ]);

        $user->update(['active_mailgun_account_id' => $account->id]);

        return $user;
    }

    private function createThread(User $user, string $subject, bool $hasUnread): MailThread
    {
        $thread = MailThread::create([
            'user_id' => $user->id,
            'subject' => $subject,
            'has_unread' => $hasUnread,
            'last_message_at' => now(),
        ]);

        MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $user->id,
            'direction' => 'inbound',
            'folder' => 'inbox',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'me@example.com']],
            'subject' => $subject,
            'status' => 'received',
            'is_read' => ! $hasUnread,
        ]);

        return $thread;
    }
}
