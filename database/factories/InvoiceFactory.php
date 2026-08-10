<?php

namespace Database\Factories;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $types = ["rent", "water", "service"];

        return [
            "user_unit_id" => "userUnitId",
            "type" => "rent",
            "amount" => "amount",
            "balance" => "amount",
            "month" => "month",
            "year" => "year",
            "created_by" => "this->id",
        ];
    }
}
