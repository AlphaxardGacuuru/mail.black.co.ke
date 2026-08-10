<?php

namespace Database\Factories;

use App\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Property>
 */
class PropertyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            "name" => fake()->streetName(),
            "user_id" => User::all()->random()->id,
            "location" => fake()->city(),
            "deposit_formula" => "r*2+2000",
            "unit_count" => rand(5, 20),
            "service_charge" => [
                "service" => rand(2, 20) * 1000,
                "electricity" => rand(2, 20) * 1000,
                "garbage" => rand(2, 20) * 1000,
                "security" => rand(2, 20) * 1000,
                "internet" => rand(2, 20) * 1000,
                "cleaning" => rand(2, 20) * 1000,
                "parking" => rand(2, 20) * 1000,
            ],
            "water_bill_rate" => [
                "council" => fake()->randomFloat(2, 1, 4),
                "borehole" => fake()->randomFloat(2, 1, 4),
                "tanker" => fake()->randomFloat(2, 1, 4),
            ],
            "invoice_date" => rand(1, 10),
            "invoice_reminder_duration" => rand(1, 10),
            "contract_terms" => fake()->paragraphs(3, true),
        ];
    }
}
