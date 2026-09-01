<?php

namespace Tests\Feature;

use App\Models\MailgunEvent;
use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_users_are_forbidden_from_admin_routes(): void
    {
        $user = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/admin/webhooks')
            ->assertForbidden();
    }

    public function test_guests_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/dashboard')->assertUnauthorized();
    }

    public function test_admin_can_view_dashboard_metrics(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $sentThread = MailThread::create(['user_id' => $admin->id, 'subject' => 'Sent mail']);
        $failedThread = MailThread::create(['user_id' => $admin->id, 'subject' => 'Failed mail']);

        MailMessage::create([
            'mail_thread_id' => $sentThread->id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'recipient@example.com']],
            'subject' => 'Sent mail',
            'status' => 'sent',
        ]);

        MailMessage::create([
            'mail_thread_id' => $failedThread->id,
            'user_id' => $admin->id,
            'direction' => 'outbound',
            'folder' => 'sent',
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'bounced@example.com']],
            'subject' => 'Failed mail',
            'status' => 'permanent_failed',
            'error_message' => 'Mailbox does not exist',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.totals.mailsSent', 1)
            ->assertJsonPath('data.totals.mailsFailed', 1)
            ->assertJsonPath('data.recentFailures.0.errorMessage', 'Mailbox does not exist');
    }

    public function test_admin_can_search_and_filter_webhooks(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);

        MailgunEvent::create([
            'provider_event_id' => 'evt-delivered-1',
            'event' => 'delivered',
            'status' => 'delivered',
            'occurred_at' => now(),
            'payload' => ['recipient' => 'match-me@example.com'],
        ]);

        MailgunEvent::create([
            'provider_event_id' => 'evt-failed-1',
            'event' => 'failed',
            'status' => 'permanent_failed',
            'occurred_at' => now(),
            'payload' => ['recipient' => 'someone@example.com'],
        ]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/webhooks?event=delivered')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.providerEventId', 'evt-delivered-1');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/webhooks?q=match-me')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.providerEventId', 'evt-delivered-1');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/webhooks?status=permanent_failed')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.providerEventId', 'evt-failed-1');
    }
}
