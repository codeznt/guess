<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use Inertia\Testing\AssertableInertia as Assert;

it('can submit single prediction with bet amount', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 100,
            ]
        ]
    ]);

    $response->assertRedirect('/dashboard')
        ->assertSessionHas('success', 'Predictions submitted successfully!');

    // Verify prediction was created
    $this->assertDatabaseHas('predictions', [
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A',
        'bet_amount' => 100,
        'potential_winnings' => 150, // 100 * 1.5 base multiplier
        'multiplier_applied' => 1.00,
    ]);

    // Verify user coins were deducted
    $user->refresh();
    expect($user->daily_coins)->toBe(900);
});

it('can submit multiple predictions at once', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question1 = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);
    $question2 = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(3),
    ]);

    $this->actingAs($user);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question1->id,
                'choice' => 'A',
                'bet_amount' => 200,
            ],
            [
                'question_id' => $question2->id,
                'choice' => 'B',
                'bet_amount' => 150,
            ]
        ]
    ]);

    $response->assertRedirect('/dashboard')
        ->assertSessionHas('success');

    // Verify both predictions were created
    expect(Prediction::count())->toBe(2);
    
    // Verify user coins were deducted
    $user->refresh();
    expect($user->daily_coins)->toBe(650); // 1000 - 200 - 150
});

it('fails when user has insufficient coins', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 50, // Not enough for bet
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 100,
            ]
        ]
    ]);

    $response->assertSessionHasErrors(['bet_amount' => 'Insufficient coins for bet']);
    
    // Verify no prediction was created
    expect(Prediction::count())->toBe(0);
    
    // Verify user coins unchanged
    $user->refresh();
    expect($user->daily_coins)->toBe(50);
});

it('fails when betting on resolved question', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved', // Already resolved
        'resolution_time' => now()->subHours(1),
        'correct_answer' => 'A',
    ]);

    $this->actingAs($user);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 100,
            ]
        ]
    ]);

    $response->assertSessionHasErrors(['question' => 'Question is no longer accepting predictions']);
    expect(Prediction::count())->toBe(0);
});

it('fails when user already has prediction for question', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    // Create existing prediction
    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'B',
        'bet_amount' => 50,
    ]);

    $this->actingAs($user);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 100,
            ]
        ]
    ]);

    $response->assertSessionHasErrors(['question' => 'You have already made a prediction for this question']);
    expect(Prediction::count())->toBe(1); // Still only the original prediction
});

it('validates bet amount limits', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    // Test minimum bet amount
    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 5, // Below minimum of 10
            ]
        ]
    ]);

    $response->assertSessionHasErrors(['bet_amount']);

    // Test maximum bet amount
    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 1001, // Above maximum of 1000
            ]
        ]
    ]);

    $response->assertSessionHasErrors(['bet_amount']);
});

it('requires authentication to submit predictions', function () {
    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $response = $this->post('/predictions', [
        'predictions' => [
            [
                'question_id' => $question->id,
                'choice' => 'A',
                'bet_amount' => 100,
            ]
        ]
    ]);

    $response->assertRedirect('/login');
    expect(Prediction::count())->toBe(0);
});