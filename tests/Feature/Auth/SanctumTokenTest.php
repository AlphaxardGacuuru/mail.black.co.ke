<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_create_sanctum_tokens_with_uuid_primary_keys(): void
    {
        $user = User::factory()->create();

        $plainTextToken = $user->createToken('test')->plainTextToken;

        $this->assertNotEmpty($plainTextToken);
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => User::class,
            'tokenable_id' => $user->id,
            'name' => 'test',
        ]);
    }
}
