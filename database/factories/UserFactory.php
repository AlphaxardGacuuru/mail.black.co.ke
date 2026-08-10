<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
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

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return static
     */
    public function unverified()
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Add Alphaxard Account
     *
     * @return static
     */
    public function super()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Super Admin',
            'email' => 'al@property.black.co.ke',
            'email_verified_at' => now(),
            'avatar' => 'avatars/male-avatar.png',
            // 'phone' => '0700364446',
            'password' => Hash::make('al@property.black.co.ke'),
            'remember_token' => Str::random(10),
            'gender' => 'male',
        ]);
    }

    /**
     * Add Al Account
     *
     * @return static
     */
    public function al()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Alphaxard Gacuuru',
            'email' => 'alphaxardgacuuru47@gmail.com',
            'email_verified_at' => now(),
            'avatar' => 'avatars/male-avatar.png',
            'phone' => '0700364446',
            'password' => Hash::make('alphaxardgacuuru47@gmail.com'),
            'remember_token' => Str::random(10),
            'gender' => 'male',
        ]);
    }

    /**
     * Add Gacuuru Account
     *
     * @return static
     */
    public function gacuuru()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Gacuuru Wa Karenge',
            'email' => 'gacuuruwakarenge@gmail.com',
            'email_verified_at' => now(),
            'avatar' => 'avatars/male-avatar.png',
            'phone' => '0722777990',
            'password' => Hash::make('gacuuruwakarenge@gmail.com'),
            'remember_token' => Str::random(10),
            'gender' => 'male',
        ]);
    }

    /**
     * Add Ciku Account
     *
     * @return static
     */
    public function ciku()
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Wanjiku Muhandi',
            'email' => 'cikumuhandi@gmail.com',
            'email_verified_at' => now(),
            'avatar' => 'avatars/male-avatar.png',
            'phone' => '0721721357',
            'password' => Hash::make('cikumuhandi@gmail.com'),
            'remember_token' => Str::random(10),
            'gender' => 'female',
        ]);
    }
}
