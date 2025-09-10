<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PredictionQuestion>
 */
class PredictionQuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $questions = [
            [
                'title' => 'Will Bitcoin reach $50,000 today?',
                'option_a' => 'Yes, above $50,000',
                'option_b' => 'No, $50,000 or below'
            ],
            [
                'title' => 'Will it rain tomorrow?',
                'option_a' => 'Yes, it will rain',
                'option_b' => 'No, it will not rain'
            ],
            [
                'title' => 'Will the Lakers win their next game?',
                'option_a' => 'Yes, Lakers will win',
                'option_b' => 'No, Lakers will lose'
            ],
            [
                'title' => 'Will Apple stock go up this week?',
                'option_a' => 'Yes, stock will rise',
                'option_b' => 'No, stock will fall'
            ],
            [
                'title' => 'Will the temperature exceed 25°C today?',
                'option_a' => 'Yes, above 25°C',
                'option_b' => 'No, 25°C or below'
            ],
        ];

        $question = $this->faker->randomElement($questions);

        return [
            'title' => $question['title'],
            'description' => $this->faker->sentence(10),
            'option_a' => $question['option_a'],
            'option_b' => $question['option_b'],
            'resolution_time' => $this->faker->dateTimeBetween('now', '+7 days'),
            'resolution_criteria' => $this->faker->sentence(15),
            'correct_answer' => $this->faker->randomElement(['A', 'B', null]),
            'status' => $this->faker->randomElement(['active', 'resolved', 'cancelled']),
            'external_reference' => $this->faker->optional()->url(),
        ];
    }
}
