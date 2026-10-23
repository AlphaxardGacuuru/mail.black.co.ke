<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationReadTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user): string
    {
        return $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\TestNotification',
            'data' => ['message' => 'Hello'],
        ])->id;
    }

    public function test_marking_one_notification_read_leaves_the_others_unread(): void
    {
        $user = User::factory()->create();
        $first = $this->notify($user);
        $this->notify($user);

        $this->actingAs($user, 'sanctum')->putJson("/api/notifications/{$first}")->assertSuccessful();

        $this->assertNotNull($user->notifications()->find($first)->read_at);
        $this->assertSame(1, $user->unreadNotifications()->count());
    }

    public function test_the_id_zero_marks_every_notification_read(): void
    {
        $user = User::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user, 'sanctum')->putJson('/api/notifications/0')->assertSuccessful();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_another_users_notification_is_not_found(): void
    {
        $owner = User::factory()->create();
        $id = $this->notify($owner);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson("/api/notifications/{$id}")
            ->assertNotFound();

        $this->assertSame(1, $owner->unreadNotifications()->count());
    }
}
