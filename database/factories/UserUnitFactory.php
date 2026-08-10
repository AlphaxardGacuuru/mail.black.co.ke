<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserUnit;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserUnit>
 */
class UserUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $users = User::whereNotIn("email", [
            "alphaxardgacuuru47@gmail.com",
            "al@property.black.co.ke",
            "gacuuruwakarenge@gmail.com",
            "cikumuhandi@gmail.com",
        ])->get();

        return [
            "user_id" => $users->random()->id,
            "occupied_at" => Carbon::now()->subMonth(2)->startOfMonth(),
            "created_by" => User::all()->random()->id,
        ];
    }
}
