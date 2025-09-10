<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use Inertia\Testing\AssertableInertia as Assert;

it('can get daily prediction questions', function () {
    // Create a user
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Test User',
        'daily_coins' => 1000,
    ]);

    // Create categories
    $cryptoCategory = Category::factory()->create([
        'name' => 'Crypto',
        'icon' => 'bitcoin',
        'color' => '#f7931a',
        'is_active' => true,
    ]);

    $sportsCategory = Category::factory()->create([
        'name' => 'Sports',
        'icon' => 'football',
        'color' => '#28a745',
        'is_active' => true,
    ]);

    // Create daily questions
    PredictionQuestion::factory()->count(10)->create([
        'category_id' => $cryptoCategory->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(6),
    ]);

    PredictionQuestion::factory()->count(2)->create([
        'category_id' => $sportsCategory->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(3),
    ]);

    // Act as the authenticated user
    $this->actingAs($user);

    // Make request to get daily questions
    $response = $this->get('/questions/daily');

    // Assert Inertia response
    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Daily')
            ->has('questions', 12)
            ->has('questions.0', fn (Assert $question) => $question
                ->has('id')
                ->has('category')
                ->has('title')
                ->has('option_a')
                ->has('option_b')
                ->has('resolution_time')
                ->has('is_resolved')
                ->has('correct_answer')
                ->has('user_prediction')
                ->where('category.name', fn ($name) => in_array($name, ['Crypto', 'Sports']))
            )
            ->where('userCoins', 1000)
            ->has('meta', fn (Assert $meta) => $meta
                ->has('date')
                ->has('total_questions')
                ->where('total_questions', 12)
                ->has('predictions_made')
                ->has('remaining_questions')
            )
        );
});

it('returns empty questions when no active questions exist', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 500,
    ]);

    $this->actingAs($user);

    $response = $this->get('/questions/daily');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Daily')
            ->has('questions', 0)
            ->where('userCoins', 500)
        );
});

it('requires authentication to access daily questions', function () {
    $response = $this->get('/questions/daily');

    $response->assertRedirect('/login');
});

it('filters out resolved and cancelled questions', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);
    $category = Category::factory()->create(['is_active' => true]);

    // Create questions with different statuses
    PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'resolution_time' => now()->subHours(2),
    ]);

    PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'cancelled',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->get('/questions/daily');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Questions/Daily')
            ->has('questions', 1) // Only the active question
        );
});