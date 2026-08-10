<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Seeder;

class PropertySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = User::query()->whereNotIn("email", [
            "al@property.black.co.ke",
            "alphaxardgacuuru47@gmail.com",
            // "gacuuruwakarenge@gmail.com",
            // "cikumuhandi@gmail.com"
        ])
            ->limit(5)
            ->get();

        foreach ($users as $user) {
            Property::factory()
                ->count(rand(1, 3))
                ->hasUnits(rand(5, 10))
                ->create(["user_id" => $user->id]);
        }
    }
}
