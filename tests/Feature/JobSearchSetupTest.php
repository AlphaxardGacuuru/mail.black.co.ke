<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobSearchSetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_step_reached_is_saved_to_pick_up_from(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->putJson('/api/job-search/setup-step', ['step' => 'preferences'])
            ->assertOk()
            ->assertJsonPath('profile.setupStep', 'preferences');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/job-search')
            ->assertJsonPath('profile.setupStep', 'preferences');
    }

    public function test_only_known_steps_are_saved(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->putJson('/api/job-search/setup-step', ['step' => 'somewhere'])
            ->assertJsonValidationErrors('step');
    }
}
