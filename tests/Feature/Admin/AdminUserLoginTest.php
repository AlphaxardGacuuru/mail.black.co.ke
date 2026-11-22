<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class AdminUserLoginTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()
            ->create(['email' => config('admin.email')]);
    }

    public function test_a_google_login_is_recorded(): void
    {
        $user = User::factory()
            ->create(['email' => 'google-user@example.com']);

        $googleUser = Mockery::mock(SocialiteUser::class);
        $googleUser->shouldReceive('getId')->andReturn('google-user-123');
        $googleUser->shouldReceive('getEmail')->andReturn('google-user@example.com');
        $googleUser->shouldReceive('getName')->andReturn($user->name);
        $googleUser->shouldReceive('getAvatar')->andReturn(null);

        $provider = Mockery::mock();
        $provider->shouldReceive('stateless')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($googleUser);

        Socialite::shouldReceive('driver')
            ->with('google')
            ->andReturn($provider);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14) Chrome/130.0')
            ->get('/login/google/callback')
            ->assertRedirect();

        $login = UserLogin::query()
            ->sole();

        $this->assertSame($user->id, $login->user_id);
        $this->assertSame(UserLogin::METHOD_GOOGLE, $login->method);
        $this->assertSame('127.0.0.1', $login->ip_address);
        $this->assertSame('Mozilla/5.0 (Linux; Android 14) Chrome/130.0', $login->user_agent);
        $this->assertNotNull($user->fresh()->last_active_at);
    }

    public function test_a_password_login_is_recorded(): void
    {
        $user = User::factory()
            ->create(['password' => Hash::make('secret-password')]);

        $this->postJson('/login', [
            'email' => $user->email,
            'password' => 'secret-password',
            'device_name' => 'test',
        ])
            ->assertOk();

        $this->assertDatabaseHas('user_logins', [
            'user_id' => $user->id,
            'method' => UserLogin::METHOD_PASSWORD,
        ]);
    }

    public function test_api_requests_mark_the_user_active_at_most_every_few_minutes(): void
    {
        $this->freezeSecond();

        $user = User::factory()
            ->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/auth')
            ->assertOk();

        $this->assertTrue($user->fresh()->last_active_at->eq(now()));

        $this->travel(User::ACTIVITY_INTERVAL_MINUTES - 1)->minutes();

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/auth')
            ->assertOk();

        $this->assertTrue($user->fresh()->last_active_at->eq(now()->subMinutes(User::ACTIVITY_INTERVAL_MINUTES - 1)));

        $this->travel(2)->minutes();

        $this->actingAs($user->fresh(), 'sanctum')
            ->getJson('/api/auth')
            ->assertOk();

        $this->assertTrue($user->fresh()->last_active_at->eq(now()));
    }

    public function test_active_users_are_counted_by_their_last_activity(): void
    {
        User::factory()
            ->create(['last_active_at' => now()->subHours(2)]);
        User::factory()
            ->create(['last_active_at' => now()->subDays(3)]);
        User::factory()
            ->create(['last_active_at' => now()->subDays(20)]);
        User::factory()
            ->create(['last_active_at' => now()->subDays(60)]);

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.activeUsers', ['day' => 1, 'week' => 2, 'month' => 3]);
    }

    public function test_admins_can_list_and_search_logins(): void
    {
        $jane = User::factory()
            ->create(['name' => 'Jane Wanjiru']);
        $tom = User::factory()
            ->create(['name' => 'Tom Otieno']);

        UserLogin::factory()
            ->for($jane)
            ->create(['created_at' => now()->subDay()]);
        UserLogin::factory()
            ->for($tom)
            ->create();

        $admin = $this->admin();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/user-logins')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.user.name', 'Tom Otieno')
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(30, 'meta.daily')
            ->assertJsonPath('meta.daily.29', ['date' => now()->toDateString(), 'logins' => 1, 'users' => 1]);

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/admin/user-logins?search=jane')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.user.name', 'Jane Wanjiru');
    }

    public function test_only_admins_can_see_logins(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/user-logins')
            ->assertForbidden();
    }

    public function test_old_logins_are_pruned(): void
    {
        $old = UserLogin::factory()
            ->create(['created_at' => now()->subDays(UserLogin::RETENTION_DAYS + 1)]);
        $recent = UserLogin::factory()
            ->create();

        $this->artisan('model:prune', ['--model' => [UserLogin::class]])
            ->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }
}
