<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\User;
use App\Models\UserProperty;
use Illuminate\Database\Seeder;

class StaffSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $properties = Property::all();

        $users = User::whereNotIn("email", [
            "alphaxardgacuuru47@gmail.com",
            "al@property.black.co.ke",
            "gacuuruwakarenge@gmail.com",
            "cikumuhandi@gmail.com",
        ])->get();

        foreach ($properties as $key => $property) {
            UserProperty::factory()
                ->create([
                    "user_id" => $users->random()->id,
                    "property_id" => $property->id,
                ]);
        }
    }
}
