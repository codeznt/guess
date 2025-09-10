<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Achievement>
 */
class AchievementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $achievementTypes = [
            'streak_milestone' => [
                'titles' => ['3-Day Streak', '5-Day Streak', '10-Day Streak', '25-Day Streak'],
                'descriptions' => ['Achieved 3 consecutive correct predictions', 'Achieved 5 consecutive correct predictions', 'Achieved 10 consecutive correct predictions', 'Achieved 25 consecutive correct predictions'],
                'icons' => ['🔥', '🔥', '⚡', '👑'],
                'points' => [30, 50, 100, 250]
            ],
            'accuracy_milestone' => [
                'titles' => ['Sharp Shooter', 'Precision Master', 'Oracle'],
                'descriptions' => ['Achieved 70% accuracy over 10 predictions', 'Achieved 80% accuracy over 20 predictions', 'Achieved 90% accuracy over 50 predictions'],
                'icons' => ['🎯', '🏹', '👁'],
                'points' => [75, 150, 500]
            ],
            'volume_milestone' => [
                'titles' => ['Beginner Predictor', 'Active Player', 'Prediction Master'],
                'descriptions' => ['Made your first 10 predictions', 'Made 100 predictions', 'Made 500 predictions'],
                'icons' => ['🌱', '📈', '🏆'],
                'points' => [25, 100, 300]
            ],
            'winnings_milestone' => [
                'titles' => ['First Win', 'Coin Collector', 'Big Winner'],
                'descriptions' => ['Won your first 100 coins', 'Won a total of 1000 coins', 'Won a total of 10000 coins'],
                'icons' => ['🪙', '💰', '💎'],
                'points' => [20, 80, 400]
            ]
        ];

        $type = $this->faker->randomElement(array_keys($achievementTypes));
        $typeData = $achievementTypes[$type];
        $index = $this->faker->numberBetween(0, count($typeData['titles']) - 1);

        return [
            'achievement_type' => $type,
            'title' => $typeData['titles'][$index],
            'description' => $typeData['descriptions'][$index],
            'icon' => $typeData['icons'][$index],
            'points_value' => $typeData['points'][$index],
            'earned_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'is_shareable' => $this->faker->boolean(80), // 80% shareable
        ];
    }
}
