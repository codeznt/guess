<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        
        return [
            'telegram_id' => fake()->unique()->numberBetween(100000000, 999999999),
            'name' => $firstName,
            'username' => fake()->optional(0.7)->userName(),
            'first_name' => $firstName,
            'last_name' => null,
            'email' => fake()->boolean(30) ? fake()->unique()->safeEmail() : null,
            'email_verified_at' => null,
            'password' => null,
            'remember_token' => null,
            'daily_coins' => fake()->numberBetween(0, 1000),
            'total_predictions' => fake()->numberBetween(0, 100),
            'correct_predictions' => fake()->numberBetween(0, 50),
            'current_streak' => fake()->numberBetween(0, 20),
            'best_streak' => fake()->numberBetween(0, 50),
            'last_active_date' => fake()->optional(0.8)->dateTimeBetween('-7 days', 'now')?->format('Y-m-d'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
