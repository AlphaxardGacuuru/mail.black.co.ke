<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The Pulse dashboard follows the Horizon gate.
 */
class PulseAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_people_who_can_see_horizon_can_see_pulse(): void
    {
        $admin = User::factory()->create();
        Gate::define('viewHorizon', fn (?User $user = null): bool => $user?->is($admin) ?? false);

        $this->actingAs($admin)->get('/pulse')->assertOk();
    }

    public function test_everyone_else_is_refused(): void
    {
        Gate::define('viewHorizon', fn (?User $user = null): bool => false);

        $this->actingAs(User::factory()->create())->get('/pulse')->assertForbidden();
        $this->get('/pulse')->assertForbidden();
    }
}
