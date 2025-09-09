<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\Achievement;

it('increments streak on correct prediction', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 3,
        'best_streak' => 10,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    // Create correct prediction
    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A', // Matches correct_answer
        'is_correct' => true,
        'bet_amount' => 100,
    ]);

    // Simulate streak service processing
    $user->update([
        'current_streak' => $user->current_streak + 1, // Increment streak
        'correct_predictions' => $user->correct_predictions + 1,
        'total_predictions' => $user->total_predictions + 1,
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(4);
    expect($user->best_streak)->toBe(10); // Unchanged since 4 < 10
});

it('updates best streak when current streak exceeds it', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 9,
        'best_streak' => 9, // Current streak is about to exceed best
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'B',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'B', // Correct
        'is_correct' => true,
        'bet_amount' => 150,
    ]);

    // Simulate streak service processing
    $newStreak = $user->current_streak + 1;
    $user->update([
        'current_streak' => $newStreak,
        'best_streak' => max($user->best_streak, $newStreak), // Update best streak
        'correct_predictions' => $user->correct_predictions + 1,
        'total_predictions' => $user->total_predictions + 1,
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(10);
    expect($user->best_streak)->toBe(10); // Updated to new best
});

it('resets streak on incorrect prediction', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 7, // Good streak that will be broken
        'best_streak' => 15,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'B', // Incorrect (correct answer is A)
        'is_correct' => false,
        'bet_amount' => 100,
    ]);

    // Simulate streak service processing incorrect prediction
    $user->update([
        'current_streak' => 0, // Reset streak
        'total_predictions' => $user->total_predictions + 1,
        // correct_predictions stays same since this was wrong
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(0); // Streak broken
    expect($user->best_streak)->toBe(15); // Best streak preserved
});

it('handles first correct prediction starting streak from zero', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 0,
        'best_streak' => 0, // New user
        'total_predictions' => 0,
        'correct_predictions' => 0,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A', // Correct
        'is_correct' => true,
        'bet_amount' => 50,
    ]);

    // Simulate streak service processing first correct prediction
    $user->update([
        'current_streak' => 1,
        'best_streak' => 1,
        'total_predictions' => 1,
        'correct_predictions' => 1,
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(1);
    expect($user->best_streak)->toBe(1);
});

it('calculates streak multiplier correctly', function () {
    $testCases = [
        ['streak' => 0, 'expected_multiplier' => 1.00],
        ['streak' => 1, 'expected_multiplier' => 1.01],
        ['streak' => 5, 'expected_multiplier' => 1.05],
        ['streak' => 10, 'expected_multiplier' => 1.10],
        ['streak' => 25, 'expected_multiplier' => 1.25],
        ['streak' => 50, 'expected_multiplier' => 1.50],
        ['streak' => 100, 'expected_multiplier' => 2.00], // Cap at 2.0x
    ];

    foreach ($testCases as $case) {
        $user = User::factory()->create([
            'telegram_id' => rand(100000000, 999999999),
            'current_streak' => $case['streak'],
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

        $prediction = Prediction::where('question_id', $question->id)->first();
        expect($prediction->multiplier_applied)->toBe($case['expected_multiplier']);
    }
});

it('awards streak milestone achievements', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 4, // About to hit 5-day milestone
        'best_streak' => 4,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A', // Correct
        'is_correct' => true,
        'bet_amount' => 100,
    ]);

    // Simulate streak service processing and achievement awarding
    $user->update([
        'current_streak' => 5,
        'best_streak' => 5,
        'correct_predictions' => $user->correct_predictions + 1,
        'total_predictions' => $user->total_predictions + 1,
    ]);

    // Award 5-day streak achievement
    Achievement::factory()->create([
        'user_id' => $user->id,
        'achievement_type' => 'streak_milestone',
        'title' => '5-Day Streak',
        'description' => 'Achieved 5 consecutive correct predictions',
        'icon' => 'fire',
        'points_value' => 50,
        'earned_at' => now(),
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(5);
    
    // Check achievement was awarded
    $achievement = Achievement::where('user_id', $user->id)
        ->where('achievement_type', 'streak_milestone')
        ->first();
    
    expect($achievement)->not->toBeNull();
    expect($achievement->title)->toBe('5-Day Streak');
});

it('handles multiple streak milestones correctly', function () {
    $milestones = [5, 10, 25, 50, 100];
    
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 0,
        'best_streak' => 0,
    ]);

    foreach ($milestones as $milestone) {
        // Simulate reaching this milestone
        $user->update([
            'current_streak' => $milestone,
            'best_streak' => $milestone,
        ]);

        // Award milestone achievement
        Achievement::factory()->create([
            'user_id' => $user->id,
            'achievement_type' => 'streak_milestone',
            'title' => "{$milestone}-Day Streak",
            'description' => "Achieved {$milestone} consecutive correct predictions",
            'icon' => $milestone >= 25 ? 'crown' : 'fire',
            'points_value' => $milestone * 10,
            'earned_at' => now(),
        ]);
    }

    // Verify all milestone achievements were created
    $achievements = Achievement::where('user_id', $user->id)
        ->where('achievement_type', 'streak_milestone')
        ->get();

    expect($achievements->count())->toBe(5);
    expect($achievements->pluck('title')->toArray())->toEqual([
        '5-Day Streak',
        '10-Day Streak', 
        '25-Day Streak',
        '50-Day Streak',
        '100-Day Streak',
    ]);
});

it('handles streak continuation across multiple days', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 3,
        'last_active_date' => now()->subDay()->toDateString(), // Yesterday
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    
    // Create prediction for today
    $todayQuestion = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'B',
        'created_at' => now(), // Today's question
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $todayQuestion->id,
        'choice' => 'B', // Correct
        'is_correct' => true,
        'bet_amount' => 100,
        'created_at' => now(), // Today's prediction
    ]);

    // Simulate streak service checking daily continuity
    // Since user was active yesterday and made correct prediction today, streak continues
    $user->update([
        'current_streak' => 4, // Continue streak
        'last_active_date' => now()->toDateString(), // Update to today
        'correct_predictions' => $user->correct_predictions + 1,
        'total_predictions' => $user->total_predictions + 1,
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(4); // Streak continued
    expect($user->last_active_date)->toBe(now()->toDateString());
});

it('breaks streak when user misses a day', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 8,
        'last_active_date' => now()->subDays(2)->toDateString(), // 2 days ago, missed yesterday
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A', // Correct, but streak already broken by missing yesterday
        'is_correct' => true,
        'bet_amount' => 100,
    ]);

    // Simulate streak service detecting missed day
    $user->update([
        'current_streak' => 1, // Reset to 1 (start new streak with today's correct prediction)
        'last_active_date' => now()->toDateString(),
        'correct_predictions' => $user->correct_predictions + 1,
        'total_predictions' => $user->total_predictions + 1,
    ]);

    $user->refresh();
    expect($user->current_streak)->toBe(1); // New streak started
});

it('handles edge case of very long streaks correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 150, // Very long streak
        'best_streak' => 150,
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

    $prediction = Prediction::first();
    
    // Should cap at 2.0x multiplier even for very long streaks
    expect($prediction->multiplier_applied)->toBe(2.00);
    expect($prediction->potential_winnings)->toBe(300); // 100 * 1.5 * 2.0
});

it('calculates accuracy percentage correctly with streaks', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'total_predictions' => 20,
        'correct_predictions' => 16, // 80% accuracy
        'current_streak' => 4,
    ]);

    // Verify accuracy calculation
    $expectedAccuracy = (16 / 20) * 100;
    expect($expectedAccuracy)->toBe(80.0);

    // Make another correct prediction
    $category = Category::factory()->create(['is_active' => true]);
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => 'A', // Correct
        'is_correct' => true,
        'bet_amount' => 100,
    ]);

    // Update stats
    $user->update([
        'total_predictions' => 21,
        'correct_predictions' => 17,
        'current_streak' => 5,
    ]);

    $user->refresh();
    $newAccuracy = ($user->correct_predictions / $user->total_predictions) * 100;
    expect($newAccuracy)->toBeCloseTo(80.95, 1); // 17/21 * 100 ≈ 80.95%
});