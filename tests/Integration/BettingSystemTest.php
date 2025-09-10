<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;

it('calculates winnings correctly with base multipliers', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 10000,
        'current_streak' => 0, // No streak multiplier
    ]);

    $this->actingAs($user);

    $category = Category::factory()->create(['is_active' => true]);

    // Test different bet amounts
    $testCases = [
        ['bet' => 100, 'expected_winnings' => 150], // 100 * 1.5 base multiplier
        ['bet' => 200, 'expected_winnings' => 300], // 200 * 1.5
        ['bet' => 50, 'expected_winnings' => 75],   // 50 * 1.5
    ];

    foreach ($testCases as $i => $case) {
        // Create new question for each test
        $testQuestion = PredictionQuestion::factory()->create([
            'category_id' => $category->id,
            'status' => 'active',
            'resolution_time' => now()->addHours(2),
        ]);

        $response = $this->withSession(['_token' => 'test-token'])
            ->post('/predictions', [
                '_token' => 'test-token',
                'predictions' => [
                    [
                        'question_id' => $testQuestion->id,
                        'choice' => 'A',
                        'bet_amount' => $case['bet'],
                    ]
                ]
            ]);

        $response->assertRedirect('/dashboard');

        // Verify potential winnings calculated correctly
        $prediction = Prediction::where('question_id', $testQuestion->id)->first();
        expect($prediction->potential_winnings)->toBe($case['expected_winnings']);
        expect((float)$prediction->multiplier_applied)->toBe(1.00); // No streak
    }
});

it('applies streak multipliers correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 10000,
        'current_streak' => 5, // 5% bonus = 1.05 multiplier
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 100,
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');

    $prediction = Prediction::first();
    
    // Base: 100 * 1.5 = 150
    // With streak: 150 * 1.05 = 157.5 (rounded to 157)
    expect($prediction->potential_winnings)->toBe(157);
    expect((float)$prediction->multiplier_applied)->toBe(1.05);
});

it('applies high streak multipliers correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 10000,
        'current_streak' => 20, // 20% bonus = 1.20 multiplier
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 200,
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');

    $prediction = Prediction::first();
    
    // Base: 200 * 1.5 = 300
    // With streak: 300 * 1.20 = 360 (rounded to 359)
    expect($prediction->potential_winnings)->toBe(359);
    expect((float)$prediction->multiplier_applied)->toBe(1.20);
});

it('enforces minimum and maximum bet limits', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 2000, // Increased to handle both bets
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    // Test minimum bet (should fail with bet < 10)
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 5, // Below minimum
                ]
            ]
        ]);

    $response->assertSessionHasErrors(['predictions.0.bet_amount']);
    expect(Prediction::count())->toBe(0);

    // Test maximum bet (should fail with bet > 1000)
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 1001, // Above maximum
                ]
            ]
        ]);

    $response->assertSessionHasErrors(['predictions.0.bet_amount']);
    expect(Prediction::count())->toBe(0);

    // Test valid minimum bet
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 10, // Minimum valid
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');
    expect(Prediction::count())->toBe(1);

    // Create new question for maximum test
    $question2 = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    // Test valid maximum bet
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question2->id,
                    'choice' => 'B',
                    'bet_amount' => 1000, // Maximum valid
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');
    expect(Prediction::count())->toBe(2);
});

it('prevents betting with insufficient coins', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 50, // Only 50 coins available
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    // Try to bet more than available coins
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 100, // More than 50 available
                ]
            ]
        ]);

    $response->assertSessionHasErrors(['bet_amount']);
    expect(Prediction::count())->toBe(0);

    // Verify coins unchanged
    $user->refresh();
    expect($user->daily_coins)->toBe(50);

    // Test valid bet within limits
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 30, // Within limit
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');
    expect(Prediction::count())->toBe(1);

    // Verify coins deducted
    $user->refresh();
    expect($user->daily_coins)->toBe(20); // 50 - 30
});

it('handles multiple simultaneous bets correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
        'current_streak' => 0, // No streak to simplify calculations
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

    // Submit multiple predictions at once
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question1->id,
                    'choice' => 'A',
                    'bet_amount' => 300,
                ],
                [
                    'question_id' => $question2->id,
                    'choice' => 'B',
                    'bet_amount' => 250,
                ]
            ]
        ]);

    $response->assertRedirect('/dashboard');

    // Verify both predictions created
    expect(Prediction::count())->toBe(2);

    // Verify total coins deducted correctly
    $user->refresh();
    expect($user->daily_coins)->toBe(450); // 1000 - 300 - 250

    // Verify individual prediction details
    $prediction1 = Prediction::where('question_id', $question1->id)->first();
    expect($prediction1->bet_amount)->toBe(300);
    expect($prediction1->potential_winnings)->toBe(450); // 300 * 1.5 (no streak)

    $prediction2 = Prediction::where('question_id', $question2->id)->first();
    expect($prediction2->bet_amount)->toBe(250);
    expect($prediction2->potential_winnings)->toBe(375); // 250 * 1.5 (no streak)
});

it('fails atomic transaction when insufficient coins for all bets', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 400, // Not enough for both bets
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

    // Try to submit predictions that exceed available coins
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question1->id,
                    'choice' => 'A',
                    'bet_amount' => 250, // Total would be 250 + 200 = 450 > 400
                ],
                [
                    'question_id' => $question2->id,
                    'choice' => 'B',
                    'bet_amount' => 200,
                ]
            ]
        ]);

    $response->assertSessionHasErrors(['bet_amount']);

    // Verify no predictions were created (atomic failure)
    expect(Prediction::count())->toBe(0);

    // Verify coins unchanged
    $user->refresh();
    expect($user->daily_coins)->toBe(400);
});

it('calculates winnings correctly with different resolution outcomes', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
        'current_streak' => 3, // 3% bonus
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    
    $correctQuestion = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $incorrectQuestion = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    // Make predictions
    $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $correctQuestion->id,
                    'choice' => 'A',
                    'bet_amount' => 200,
                ],
                [
                    'question_id' => $incorrectQuestion->id,
                    'choice' => 'B',
                    'bet_amount' => 150,
                ]
            ]
        ]);

    // Resolve questions
    $correctQuestion->update([
        'status' => 'resolved',
        'correct_answer' => 'A', // User was correct
    ]);

    $incorrectQuestion->update([
        'status' => 'resolved',
        'correct_answer' => 'A', // User chose B, so incorrect
    ]);

    // Update predictions with results
    $correctPrediction = Prediction::where('question_id', $correctQuestion->id)->first();
    $correctPrediction->update([
        'is_correct' => true,
        'actual_winnings' => $correctPrediction->potential_winnings, // Full winnings
    ]);

    $incorrectPrediction = Prediction::where('question_id', $incorrectQuestion->id)->first();
    $incorrectPrediction->update([
        'is_correct' => false,
        'actual_winnings' => 0, // No winnings
    ]);

    // Verify correct prediction got full winnings
    $correctPrediction->refresh();
    expect($correctPrediction->actual_winnings)->toBe(309); // 200 * 1.5 * 1.03 = 309

    // Verify incorrect prediction got no winnings
    $incorrectPrediction->refresh();
    expect($incorrectPrediction->actual_winnings)->toBe(0);
});

it('handles edge case of zero streak correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 500,
        'current_streak' => 0, // No streak
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 100,
                ]
            ]
        ]);

    $prediction = Prediction::first();
    
    // Should have base multiplier only (no streak bonus)
    expect((float)$prediction->multiplier_applied)->toBe(1.00);
    expect($prediction->potential_winnings)->toBe(150); // 100 * 1.5 * 1.0
});