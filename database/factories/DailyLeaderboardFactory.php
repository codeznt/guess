<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DailyLeaderboard>
 */
class DailyLeaderboardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $predictionsMade = $this->faker->numberBetween(1, 20);
        $correctPredictions = $this->faker->numberBetween(0, $predictionsMade);
        $accuracyPercentage = $predictionsMade > 0 ? round(($correctPredictions / $predictionsMade) * 100, 2) : 0;
        
        return [
            'user_id' => \App\Models\User::factory(),
            'leaderboard_date' => $this->faker->dateTimeBetween('-7 days', 'now')->format('Y-m-d'),
            'total_winnings' => $this->faker->numberBetween(0, 5000),
            'predictions_made' => $predictionsMade,
            'correct_predictions' => $correctPredictions,
            'accuracy_percentage' => $accuracyPercentage,
            'rank' => $this->faker->numberBetween(1, 100),
        ];
    }
}
