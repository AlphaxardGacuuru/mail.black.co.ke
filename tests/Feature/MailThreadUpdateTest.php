<?php

namespace Tests\Feature;

use App\Models\MailMessage;
use App\Models\MailThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailThreadUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_can_be_moved_to_archive_through_the_update_endpoint(): void
    {

        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Project update']);
        $message = $this->createMessage($thread, 'inbound', 'inbox');

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['folder' => 'archive']);

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame('archive', $message->refresh()->folder);
    }

    public function test_thread_restore_uses_message_direction_through_the_update_endpoint(): void
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Mixed thread']);
        $inboundMessage = $this->createMessage($thread, 'inbound', 'archive');
        $outboundMessage = $this->createMessage($thread, 'outbound', 'archive');

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['folder' => 'inbox']);

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertSame('inbox', $inboundMessage->refresh()->folder);
        $this->assertSame('sent', $outboundMessage->refresh()->folder);
    }

    public function test_restore_returns_thread_to_its_previous_folder_through_the_history_stack(): void
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Bounced around']);
        $message = $this->createMessage($thread, 'inbound', 'inbox');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['folder' => 'archive'])
            ->assertOk();
        $this->assertSame('archive', $message->refresh()->folder);
        $this->assertSame([['folder' => 'inbox']], array_map(
            fn ($entry) => ['folder' => $entry['folder']],
            $message->folder_history
        ));

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['folder' => 'trash'])
            ->assertOk();
        $this->assertSame('trash', $message->refresh()->folder);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['restore' => true])
            ->assertOk();
        $this->assertSame('archive', $message->refresh()->folder);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", ['restore' => true])
            ->assertOk();
        $this->assertSame('inbox', $message->refresh()->folder);
        $this->assertSame([], $message->folder_history);
    }

    public function test_thread_star_and_read_state_can_be_updated_through_the_update_endpoint(): void
    {
        $user = User::factory()->create();
        $thread = MailThread::create(['user_id' => $user->id, 'subject' => 'Thread state']);
        $message = $this->createMessage($thread, 'inbound', 'inbox');

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson("/api/threads/{$thread->id}", [
                'isStarred' => true,
                'isRead' => true,
            ]);

        $response->assertOk()->assertJson(['status' => true]);
        $this->assertTrue($thread->refresh()->is_starred);
        $this->assertTrue($message->refresh()->is_starred);
        $this->assertTrue($message->is_read);
    }

    private function createMessage(MailThread $thread, string $direction, string $folder): MailMessage
    {
        return MailMessage::create([
            'mail_thread_id' => $thread->id,
            'user_id' => $thread->user_id,
            'direction' => $direction,
            'folder' => $folder,
            'from_address' => ['address' => 'sender@example.com'],
            'to' => [['address' => 'recipient@example.com']],
            'subject' => $thread->subject,
            'status' => 'received',
        ]);
    }
}
