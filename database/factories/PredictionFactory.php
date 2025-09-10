<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Prediction>
 */
class PredictionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $choice = $this->faker->randomElement(['A', 'B']);
        $betAmount = $this->faker->numberBetween(10, 500);
        $baseMultiplier = 1.5;
        $streakMultiplier = $this->faker->randomFloat(2, 1.0, 2.0);
        $potentialWinnings = round($betAmount * $baseMultiplier * $streakMultiplier);

        return [
            'choice' => $choice,
            'bet_amount' => $betAmount,
            'potential_winnings' => $potentialWinnings,
            'actual_winnings' => $this->faker->optional(0.6, 0)->numberBetween(0, $potentialWinnings),
            'multiplier_applied' => $streakMultiplier,
            'is_correct' => $this->faker->boolean(60), // 60% chance correct
        ];
    }
}
