<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatUserSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_finds_other_people_without_their_accounts(): void
    {
        $user = User::factory()->create(['name' => 'Wanjiku Kamau']);
        User::factory()->create(['name' => 'Otieno Ouma']);
        User::factory()->create(['name' => 'Akinyi Ouma']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/chat/users?name=ouma')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Akinyi Ouma')
            ->assertJsonStructure(['data' => [['id', 'name', 'email', 'avatar', 'verified']]])
            ->assertJsonMissingPath('data.0.mailgunAccounts');
    }

    public function test_search_leaves_out_the_user_searching(): void
    {
        $user = User::factory()->create(['name' => 'Wanjiku Kamau']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/chat/users?name=wanjiku')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
