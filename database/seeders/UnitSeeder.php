<?php

namespace Database\Seeders;

use App\Models\Property;
use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $properties = Property::all();

        foreach ($properties as $property) {
            Unit::factory()
                ->count(rand(5, 20))
                ->create([
                    "property_id" => $property->id,
                ]);
        }
    }
}
