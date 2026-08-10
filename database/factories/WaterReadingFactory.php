<?php

namespace Database\Factories;

use App\Models\WaterReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WaterReading>
 */
class WaterReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $types = ["council", "borehole", "tanker"];

        return [
            "user_unit_id" => "userUnitId",
            "type" => $types[rand(0, 2)],
            "reading" => "reading",
            "month" => "month",
            "year" => "year",
            "usage" => "usage",
            "bill" => "bill",
        ];
    }
}
