<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserLogin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserLogin>
 */
class UserLoginFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'method' => UserLogin::METHOD_GOOGLE,
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
