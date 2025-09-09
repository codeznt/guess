<?php

use App\Models\User;
use App\Models\Achievement;
use Inertia\Testing\AssertableInertia as Assert;

it('can get user profile statistics', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'John',
        'username' => 'johnsmith',
        'daily_coins' => 750,
        'total_predictions' => 247,
        'correct_predictions' => 189,
        'current_streak' => 7,
        'best_streak' => 23,
    ]);

    // Create some achievements for the user
    Achievement::factory()->create([
        'user_id' => $user->id,
        'achievement_type' => 'perfect_day',
        'title' => 'Perfect Day',
        'description' => 'Achieved 100% accuracy for a day',
        'icon' => 'trophy',
        'points_value' => 100,
        'earned_at' => now()->subDays(2),
    ]);

    Achievement::factory()->create([
        'user_id' => $user->id,
        'achievement_type' => 'streak_milestone',
        'title' => 'Hot Streak',
        'description' => 'Achieved 5-day prediction streak',
        'icon' => 'fire',
        'points_value' => 50,
        'earned_at' => now()->subDay(),
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Stats')
            ->has('userStats', fn (Assert $stats) => $stats
                ->has('user', fn (Assert $userData) => $userData
                    ->where('id', $user->id)
                    ->where('first_name', 'John')
                    ->where('username', 'johnsmith')
                )
                ->where('dailyCoins', 750)
                ->where('totalPredictions', 247)
                ->where('correctPredictions', 189)
                ->where('accuracyPercentage', 76.5) // 189/247 * 100
                ->where('currentStreak', 7)
                ->where('bestStreak', 23)
                ->where('totalEarnings', 0) // Will be calculated from predictions
                ->has('achievements', 2)
                ->has('achievements.0', fn (Assert $achievement) => $achievement
                    ->where('title', 'Perfect Day')
                    ->where('description', 'Achieved 100% accuracy for a day')
                    ->where('icon', 'trophy')
                    ->has('earned_at')
                )
            )
        );
});

it('calculates accuracy percentage correctly', function () {
    // Test with 0 predictions
    $userNoPredictions = User::factory()->create([
        'telegram_id' => 111111111,
        'total_predictions' => 0,
        'correct_predictions' => 0,
    ]);

    $this->actingAs($userNoPredictions);
    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('userStats.accuracyPercentage', 0.0)
        );

    // Test with perfect accuracy
    $userPerfect = User::factory()->create([
        'telegram_id' => 222222222,
        'total_predictions' => 50,
        'correct_predictions' => 50,
    ]);

    $this->actingAs($userPerfect);
    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('userStats.accuracyPercentage', 100.0)
        );
});

it('shows recent achievements in chronological order', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    // Create achievements with different earned dates
    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'First Achievement',
        'earned_at' => now()->subDays(5),
    ]);

    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Latest Achievement',
        'earned_at' => now()->subDay(),
    ]);

    Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Middle Achievement',
        'earned_at' => now()->subDays(3),
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('userStats.achievements', 3)
            ->where('userStats.achievements.0.title', 'Latest Achievement') // Most recent first
            ->where('userStats.achievements.1.title', 'Middle Achievement')
            ->where('userStats.achievements.2.title', 'First Achievement')
        );
});

it('shows empty achievements when user has none', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'total_predictions' => 10,
        'correct_predictions' => 5,
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Profile/Stats')
            ->has('userStats.achievements', 0)
        );
});

it('includes streak information and milestones', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'current_streak' => 12,
        'best_streak' => 25,
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('userStats.currentStreak', 12)
            ->where('userStats.bestStreak', 25)
            ->has('streakInfo', fn (Assert $streak) => $streak
                ->where('currentStreak', 12)
                ->where('bestStreak', 25)
                ->where('streakMultiplier', 1.12) // 1 + (12 * 0.01)
                ->where('nextMilestone', 15) // Next milestone after 12
            )
        );
});

it('shows profile for user without username', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Anonymous',
        'username' => null, // No username
        'daily_coins' => 500,
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('userStats.user.first_name', 'Anonymous')
            ->where('userStats.user.username', null)
            ->where('userStats.dailyCoins', 500)
        );
});

it('calculates total earnings from prediction history', function () {
    // This test assumes that total earnings will be calculated
    // from the user's prediction winnings history
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'total_predictions' => 20,
        'correct_predictions' => 15,
    ]);

    $this->actingAs($user);

    $response = $this->get('/profile');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('userStats.totalEarnings') // Will be calculated based on predictions
        );
});

it('requires authentication to view profile', function () {
    $response = $this->get('/profile');

    $response->assertRedirect('/login');
});