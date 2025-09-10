<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $categories = [
            ['name' => 'Cryptocurrency', 'icon' => '₿', 'color' => '#f7931a'],
            ['name' => 'Sports', 'icon' => '⚽', 'color' => '#28a745'], 
            ['name' => 'Weather', 'icon' => '🌤', 'color' => '#17a2b8'],
            ['name' => 'Pop Culture', 'icon' => '🎬', 'color' => '#6f42c1'],
            ['name' => 'Technology', 'icon' => '💻', 'color' => '#20c997'],
            ['name' => 'Economics', 'icon' => '📈', 'color' => '#fd7e14'],
        ];

        $category = $this->faker->randomElement($categories);

        return [
            'name' => $category['name'],
            'description' => $this->faker->sentence(8),
            'icon' => $category['icon'],
            'color' => $category['color'],
            'is_active' => $this->faker->boolean(90), // 90% chance active
        ];
    }
}
