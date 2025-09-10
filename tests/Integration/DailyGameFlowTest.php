<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\Achievement;
use App\Models\DailyLeaderboard;

it('completes full daily game flow from dashboard to results', function () {
    // Setup: Create user
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Game Player',
        'daily_coins' => 1000,
        'current_streak' => 2, // Existing streak
    ]);

    // Setup: Create categories and questions
    $cryptoCategory = Category::factory()->create([
        'name' => 'Crypto',
        'is_active' => true,
    ]);

    $sportsCategory = Category::factory()->create([
        'name' => 'Sports',
        'is_active' => true,
    ]);

    $question1 = PredictionQuestion::factory()->create([
        'category_id' => $cryptoCategory->id,
        'title' => 'Will Bitcoin reach $50,000 today?',
        'option_a' => 'Yes, above $50,000',
        'option_b' => 'No, $50,000 or below',
        'status' => 'active',
        'resolution_time' => now()->addHours(6),
    ]);

    $question2 = PredictionQuestion::factory()->create([
        'category_id' => $sportsCategory->id,
        'title' => 'Will Lakers win tonight?',
        'option_a' => 'Yes, Lakers win',
        'option_b' => 'No, Lakers lose',
        'status' => 'active',
        'resolution_time' => now()->addHours(4),
    ]);

    $this->actingAs($user);

    // Step 1: View dashboard - should show daily questions
    $dashboardResponse = $this->get('/dashboard');
    $dashboardResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('dailyQuestions', 2)
            ->where('userStats.dailyCoins', 1000)
        );

    // Step 2: View daily questions page
    $questionsResponse = $this->get('/questions/daily');
    $questionsResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Questions/Daily')
            ->has('questions', 2)
        );

    // Step 3: Submit predictions
    $predictionsResponse = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
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

    $predictionsResponse->assertRedirect('/dashboard')
        ->assertSessionHas('success');

    // Verify predictions were created
    expect(Prediction::count())->toBe(2);
    
    // Verify user coins were deducted
    $user->refresh();
    expect($user->daily_coins)->toBe(650); // 1000 - 200 - 150

    // Step 4: Return to dashboard - should show updated state
    $updatedDashboardResponse = $this->get('/dashboard');
    $updatedDashboardResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userStats.dailyCoins', 650)
            ->where('todayStats.predictionsToday', 2)
            ->where('todayStats.coinsSpentToday', 350)
        );

    // Step 5: Simulate question resolution (correct prediction on question1)
    $question1->update([
        'status' => 'resolved',
        'correct_answer' => 'A', // User chose A - correct!
    ]);

    $question2->update([
        'status' => 'resolved', 
        'correct_answer' => 'A', // User chose B - incorrect!
    ]);

    // Step 6: Process prediction results (this would normally be done by a job/service)
    $prediction1 = Prediction::where('question_id', $question1->id)->first();
    $prediction1->update([
        'is_correct' => true,
        'actual_winnings' => 300, // Won with streak multiplier
    ]);

    $prediction2 = Prediction::where('question_id', $question2->id)->first();
    $prediction2->update([
        'is_correct' => false,
        'actual_winnings' => 0, // Lost
    ]);

    // Step 7: Update user stats (normally done by service)
    $user->update([
        'total_predictions' => $user->total_predictions + 2,
        'correct_predictions' => $user->correct_predictions + 1, // Only question1 correct
        'current_streak' => 3, // Streak continues with correct prediction
        'daily_coins' => $user->daily_coins + 300, // Add winnings
    ]);

    // Step 8: View profile to see updated stats
    $profileResponse = $this->get('/profile');
    $profileResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Profile/Stats')
            ->where('userStats.currentStreak', 3)
            ->where('userStats.dailyCoins', 950) // 650 + 300 winnings
        );

    // Step 9: Generate leaderboard from predictions and check position
    DailyLeaderboard::generateDailyLeaderboard(now()->toDateString());

    $leaderboardResponse = $this->get('/leaderboard');
    $leaderboardResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Daily')
            ->where('userRank', 1) // User should be ranked 1st with their winnings
        );
});

it('handles streak breaking scenario', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 500,
        'current_streak' => 5, // Good streak that will break
        'best_streak' => 10,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(2),
    ]);

    $this->actingAs($user);

    // Make prediction
    $this->withSession(['_token' => 'test-token'])
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

    // Simulate wrong answer - breaks streak
    $question->update([
        'status' => 'resolved',
        'correct_answer' => 'B', // User chose A - wrong!
    ]);

    $prediction = Prediction::where('question_id', $question->id)->first();
    $prediction->update([
        'is_correct' => false,
        'actual_winnings' => 0,
    ]);

    // Update user - streak broken
    $user->update([
        'total_predictions' => $user->total_predictions + 1,
        'current_streak' => 0, // Streak broken
    ]);

    // Check dashboard shows broken streak
    $response = $this->get('/dashboard');
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userStats.currentStreak', 0)
            ->where('userStats.bestStreak', 10) // Best streak unchanged
        );
});

it('handles multiple users competing on same day', function () {
    // Create multiple users
    $user1 = User::factory()->create([
        'telegram_id' => 111111111,
        'first_name' => 'Player One',
        'daily_coins' => 1000,
    ]);

    $user2 = User::factory()->create([
        'telegram_id' => 222222222,
        'first_name' => 'Player Two',
        'daily_coins' => 1000,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    
    $question = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(3),
    ]);

    // Both users make predictions
    $this->actingAs($user1);
    $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 300, // Higher bet
                ]
            ]
        ]);

    $this->actingAs($user2);
    $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $question->id,
                    'choice' => 'A',
                    'bet_amount' => 200, // Lower bet
                ]
            ]
        ]);

    // Resolve question - both correct
    $question->update([
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    // Update predictions with results
    Prediction::where('user_id', $user1->id)->update([
        'is_correct' => true,
        'actual_winnings' => 450, // 300 * 1.5 = 450
    ]);

    Prediction::where('user_id', $user2->id)->update([
        'is_correct' => true,
        'actual_winnings' => 300, // 200 * 1.5 = 300
    ]);

    // Generate leaderboard from predictions
    DailyLeaderboard::generateDailyLeaderboard(now()->toDateString());

    // Check leaderboard shows correct rankings
    $this->actingAs($user1);
    $leaderboardResponse = $this->get('/leaderboard');
    $leaderboardResponse->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 2)
            ->where('rankings.0.user.first_name', 'Player One')
            ->where('rankings.0.total_winnings', 450)
            ->where('rankings.0.rank', 1)
            ->where('userRank', 1)
        );
});

it('prevents predictions after resolution time', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 500,
    ]);

    $category = Category::factory()->create(['is_active' => true]);
    
    $expiredQuestion = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->subMinutes(30), // Already past resolution time
    ]);

    $this->actingAs($user);

    // Try to make prediction on expired question
    $response = $this->withSession(['_token' => 'test-token'])
        ->post('/predictions', [
            '_token' => 'test-token',
            'predictions' => [
                [
                    'question_id' => $expiredQuestion->id,
                    'choice' => 'A',
                    'bet_amount' => 100,
                ]
            ]
        ]);

    // Should fail with error
    $response->assertSessionHasErrors();
    
    // Verify no prediction was created
    expect(Prediction::count())->toBe(0);
    
    // Verify user coins unchanged
    $user->refresh();
    expect($user->daily_coins)->toBe(500);
});