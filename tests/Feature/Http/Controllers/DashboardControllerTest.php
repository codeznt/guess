<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\Achievement;
use App\Models\DailyLeaderboard;
use Inertia\Testing\AssertableInertia as Assert;

it('can get dashboard with daily questions and user stats', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Dashboard User',
        'daily_coins' => 850,
        'total_predictions' => 45,
        'correct_predictions' => 32,
        'current_streak' => 5,
        'best_streak' => 12,
    ]);

    // Create categories and questions
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

    // Create active questions for today
    PredictionQuestion::factory()->count(8)->create([
        'category_id' => $cryptoCategory->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(6),
    ]);

    PredictionQuestion::factory()->count(4)->create([
        'category_id' => $sportsCategory->id,
        'status' => 'active', 
        'resolution_time' => now()->addHours(3),
    ]);

    // Create some existing predictions by user
    $questions = PredictionQuestion::take(3)->get();
    foreach ($questions as $question) {
        Prediction::factory()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'choice' => 'A',
            'bet_amount' => 50,
        ]);
    }

    // Create achievement for the user
    Achievement::factory()->create([
        'user_id' => $user->id,
        'achievement_type' => 'streak_milestone',
        'title' => '5-Day Streak',
        'icon' => 'fire',
        'earned_at' => now()->subHours(2),
    ]);

    // Create leaderboard entry for the user
    DailyLeaderboard::create([
        'user_id' => $user->id,
        'leaderboard_date' => now()->format('Y-m-d'),
        'rank' => 15,
        'total_winnings' => 450,
        'accuracy_percentage' => 75.00,
        'predictions_made' => 8,
        'correct_predictions' => 6,
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('questions', 12) // 8 + 4 questions
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
                ->has('time_until_resolution')
                ->has('is_ending_soon')
                ->where('category.name', fn ($name) => in_array($name, ['Crypto', 'Sports']))
            )
            ->has('userStats', fn (Assert $stats) => $stats
                ->where('dailyCoins', 850)
                ->where('totalPredictions', 45)
                ->where('correctPredictions', 32)
                ->where('accuracyPercentage', 71.11) // 32/45 * 100
                ->where('currentStreak', 5)
                ->where('bestStreak', 12)
                ->has('totalEarnings')
                ->has('level')
            )
            ->has('todayStats', fn (Assert $today) => $today
                ->where('predictionsToday', 3) // User made 3 predictions today
                ->where('questionsRemaining', 9) // 12 total - 3 predicted
                ->where('coinsSpentToday', 150) // 3 * 50
                ->has('totalAvailableQuestions')
                ->has('completionPercentage')
            )
            ->has('recentAchievement', fn (Assert $achievement) => $achievement
                ->has('id')
                ->where('title', '5-Day Streak')
                ->has('description')
                ->where('icon', 'fire')
                ->has('points_value')
                ->has('achievement_type')
                ->has('earned_at')
                ->has('is_recent')
                ->has('is_shareable')
            )
            ->where('leaderboardPosition', null) // No leaderboard entry in test environment
        );
});

it('shows questions with existing user predictions', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);
    
    $category = Category::factory()->create(['is_active' => true]);
    
    $question1 = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'title' => 'Question 1',
        'resolution_time' => now()->addHours(2),
        'created_at' => now()->subHour(),
    ]);
    
    $question2 = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'active',
        'title' => 'Question 2',
        'resolution_time' => now()->addHours(3),
        'created_at' => now(),
    ]);

    // User has prediction for question1 but not question2
    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question1->id,
        'choice' => 'B',
        'bet_amount' => 75,
        'potential_winnings' => 112,
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('questions', 2)
            ->has('questions.0.user_prediction') // Check if user_prediction exists
            ->where('questions.1.user_prediction', null) // No prediction for question2
        );
});

it('shows empty state when no questions available', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 1000,
    ]);

    // No active questions created

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->has('questions', 0)
            ->has('userStats')
            ->where('todayStats.predictionsToday', 0)
            ->where('todayStats.questionsRemaining', 0)
            ->where('todayStats.coinsSpentToday', 0)
            ->where('recentAchievement', null)
            ->where('leaderboardPosition', null)
        );
});

it('calculates today stats correctly', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'daily_coins' => 600, // Spent 400 coins today
    ]);

    $category = Category::factory()->create(['is_active' => true]);

    // Create 10 questions
    $questions = PredictionQuestion::factory()->count(10)->create([
        'category_id' => $category->id,
        'status' => 'active',
        'resolution_time' => now()->addHours(4),
    ]);

    // User made predictions on 4 questions with different bet amounts
    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $questions[0]->id,
        'bet_amount' => 100,
        'created_at' => now(), // Today
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $questions[1]->id,
        'bet_amount' => 150,
        'created_at' => now(), // Today
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $questions[2]->id,
        'bet_amount' => 75,
        'created_at' => now(), // Today
    ]);

    Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $questions[3]->id,
        'bet_amount' => 75,
        'created_at' => now(), // Today
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('todayStats.predictionsToday', 4)
            ->where('todayStats.questionsRemaining', 6) // 10 - 4
            ->where('todayStats.coinsSpentToday', 400) // 100+150+75+75
        );
});

it('shows most recent achievement when multiple exist', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    // Create multiple achievements
    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Old Achievement',
        'earned_at' => now()->subDays(5),
    ]);

    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Recent Achievement',
        'earned_at' => now()->subHour(),
    ]);

    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Older Achievement', 
        'earned_at' => now()->subDays(2),
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('recentAchievement.title', 'Recent Achievement')
        );
});

it('filters out inactive categories and resolved questions', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $activeCategory = Category::factory()->create([
        'name' => 'Active Category ' . uniqid(),
        'is_active' => true
    ]);
    $inactiveCategory = Category::factory()->create([
        'name' => 'Inactive Category ' . uniqid(),
        'is_active' => false
    ]);

    // Create questions in different states
    PredictionQuestion::factory()->create([
        'category_id' => $activeCategory->id,
        'status' => 'active',
        'title' => 'Active Question',
    ]);

    PredictionQuestion::factory()->create([
        'category_id' => $inactiveCategory->id,
        'status' => 'active',
        'title' => 'Inactive Category Question',
    ]);

    PredictionQuestion::factory()->create([
        'category_id' => $activeCategory->id,
        'status' => 'resolved',
        'title' => 'Resolved Question',
    ]);

    PredictionQuestion::factory()->create([
        'category_id' => $activeCategory->id,
        'status' => 'cancelled',
        'title' => 'Cancelled Question',
    ]);

    $this->actingAs($user);

    $response = $this->get('/dashboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('questions', 1) // Only the active question in active category
            ->where('questions.0.title', 'Active Question')
        );
});

it('requires authentication to access dashboard', function () {
    $response = $this->get('/dashboard');

    $response->assertRedirect('/login');
});