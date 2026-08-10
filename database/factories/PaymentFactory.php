<?php

namespace Database\Factories;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        $channels = ["Bank", "Mpesa"];

        return [
            "amount" => "amount",
            // "transaction_reference" => fake()->regexify('[A-Z0-9]{10}'),
            "channel" => $channels[rand(0, 1)],
            "month" => Carbon::now()->format('m'),
            "year" => Carbon::now()->format('y'),
            "created_by" => Carbon::now()->timestamp,
        ];
    }
}
