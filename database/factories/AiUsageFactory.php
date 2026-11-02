<?php

namespace Database\Factories;

use App\Models\AiUsage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AiUsage>
 */
class AiUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'feature' => AiUsage::FEATURE_REPLY_SUGGESTION,
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash-lite',
            'attempt' => null,
            'input_tokens' => fake()->numberBetween(1000, 3000),
            'cached_input_tokens' => 0,
            'output_tokens' => fake()->numberBetween(100, 600),
            'reasoning_tokens' => 0,
            'cost_micros' => fake()->numberBetween(100, 600),
        ];
    }

    /**
     * Scoring a batch of job postings.
     */
    public function jobMatch(): static
    {
        return $this->state(fn (): array => ['feature' => AiUsage::FEATURE_JOB_MATCH]);
    }

    /**
     * A careers page search shared between users, so it belongs to no one.
     */
    public function system(): static
    {
        return $this->state(fn (): array => ['user_id' => null, 'feature' => AiUsage::FEATURE_CAREER_PAGE_SEARCH]);
    }
}
