<?php

namespace Database\Factories;

use App\Models\Staff;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<Staff>
 */
class StaffFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $email = fake()->unique()->safeEmail();

        $gender = ["male", "female"];

        return [
            'name' => fake()->name(),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($email),
            'remember_token' => Str::random(10),
            'phone' => fake()->phoneNumber(),
            'avatar' => 'avatars/male-avatar.png',
            'gender' => $gender[rand(0, 1)],
            'created_at' => Carbon::now()->subDay(rand(3, 12)),
        ];
    }
}
