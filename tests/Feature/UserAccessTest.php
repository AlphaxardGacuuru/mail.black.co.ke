<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_list_everyone(): void
    {
        $other = User::factory()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/users')
            ->assertDontSee($other->email);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/users')
            ->assertForbidden();
    }

    public function test_a_user_cannot_see_or_change_someone_else(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create(['name' => 'Other Person']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users/'.$other->id)
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/users/'.$other->id, ['name' => 'Changed'])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->deleteJson('/api/admin/users/'.$other->id)
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $other->id, 'name' => 'Other Person']);
    }

    public function test_a_user_can_see_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/users/'.$user->id)
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }

    public function test_the_admin_can_verify_a_user_and_take_it_back(): void
    {
        $admin = User::factory()->create(['email' => config('admin.email')]);
        $user = User::factory()->create();

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", ['verified' => true])
            ->assertOk()
            ->assertJsonPath('message', 'User Verified')
            ->assertJsonPath('data.verified', true);

        $this->assertTrue($user->fresh()->verified);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/users')
            ->assertJsonFragment(['id' => $user->id, 'verified' => true]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", ['verified' => false])
            ->assertJsonPath('message', 'Verification Removed');

        $this->assertFalse($user->fresh()->verified);
    }

    public function test_users_cannot_verify_themselves(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/admin/users/{$user->id}/verify", ['verified' => true])
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/users/'.$user->id, ['verified' => true, 'userRoles' => ['admin']])
            ->assertOk();

        $this->assertFalse((bool) $user->fresh()->verified);
        $this->assertCount(0, $user->fresh()->roles);
    }
}
