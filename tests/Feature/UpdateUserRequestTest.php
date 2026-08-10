<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateUserRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_update_accepts_nullable_fields_and_preserves_existing_values(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing Name',
            'email' => 'existing@example.com',
            'phone' => '0712345678',
        ]);

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/users/' . $user->id, [
                'name' => null,
                'email' => null,
                'phone' => '0798765432',
            ])
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Existing Name',
            'email' => 'existing@example.com',
            'phone' => '0798765432',
        ]);
    }
}
