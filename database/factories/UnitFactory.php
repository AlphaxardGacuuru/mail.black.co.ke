<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $block = ["A", "B", "C", "D", "E", "F", "G"];

        $rent = rand(8, 100) * 1000;

        return [
            "name" => $block[rand(0, 6)].rand(0, 20),
            "rent" => $rent,
            "deposit" => (($rent * rand(2, 3))),
            "type" => "apartment",
            "bedrooms" => rand(1, 5),
            "ensuite" => rand(0, 3),
            "dsq" => rand(0, 1),
        ];
    }
}
