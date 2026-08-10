<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $gacuuruDoesntExist = User::where('email', 'gacuuruwakarenge@gmail.com')
            ->doesntExist();

        $cikuDoesntExist = User::where('email', 'cikumuhandi@gmail.com')
            ->doesntExist();

        if ($gacuuruDoesntExist) {
            User::factory()->gacuuru()->create();
        }

        if ($cikuDoesntExist) {
            User::factory()->ciku()->create();
        }

        User::factory()->count(50)->create();
    }
}
