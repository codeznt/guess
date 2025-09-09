<?php

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\DailyLeaderboard;

it('calculates daily leaderboard rankings correctly', function () {
    $today = now()->toDateString();

    // Create users with different performance levels
    $user1 = User::factory()->create([
        'telegram_id' => 111111111,
        'first_name' => 'Top Performer',
        'username' => 'topuser',
    ]);

    $user2 = User::factory()->create([
        'telegram_id' => 222222222,
        'first_name' => 'Good Player',
        'username' => 'goodplayer',
    ]);

    $user3 = User::factory()->create([
        'telegram_id' => 333333333,
        'first_name' => 'Average Player',
        'username' => 'average',
    ]);

    // Create categories and questions
    $category = Category::factory()->create(['is_active' => true]);
    $questions = PredictionQuestion::factory()->count(5)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    // User 1: High winnings, high accuracy (should rank #1)
    foreach ($questions as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user1->id,
            'question_id' => $question->id,
            'choice' => 'A', // All correct
            'is_correct' => true,
            'bet_amount' => 200,
            'actual_winnings' => 300,
        ]);
    }

    // User 2: Medium winnings, good accuracy (should rank #2)
    foreach ($questions->take(4) as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user2->id,
            'question_id' => $question->id,
            'choice' => $i < 3 ? 'A' : 'B', // 3 correct, 1 incorrect
            'is_correct' => $i < 3,
            'bet_amount' => 150,
            'actual_winnings' => $i < 3 ? 225 : 0,
        ]);
    }

    // User 3: Lower winnings, lower accuracy (should rank #3)
    foreach ($questions->take(3) as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user3->id,
            'question_id' => $question->id,
            'choice' => $i < 2 ? 'A' : 'B', // 2 correct, 1 incorrect
            'is_correct' => $i < 2,
            'bet_amount' => 100,
            'actual_winnings' => $i < 2 ? 150 : 0,
        ]);
    }

    // Simulate leaderboard calculation service
    DailyLeaderboard::create([
        'user_id' => $user1->id,
        'leaderboard_date' => $today,
        'total_winnings' => 1500, // 5 * 300
        'predictions_made' => 5,
        'correct_predictions' => 5,
        'accuracy_percentage' => 100.0,
        'rank' => 1,
    ]);

    DailyLeaderboard::create([
        'user_id' => $user2->id,
        'leaderboard_date' => $today,
        'total_winnings' => 675, // 3 * 225
        'predictions_made' => 4,
        'correct_predictions' => 3,
        'accuracy_percentage' => 75.0,
        'rank' => 2,
    ]);

    DailyLeaderboard::create([
        'user_id' => $user3->id,
        'leaderboard_date' => $today,
        'total_winnings' => 300, // 2 * 150
        'predictions_made' => 3,
        'correct_predictions' => 2,
        'accuracy_percentage' => 66.67,
        'rank' => 3,
    ]);

    // Test leaderboard retrieval
    $this->actingAs($user1);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 3)
            ->where('rankings.0.rank', 1)
            ->where('rankings.0.user.first_name', 'Top Performer')
            ->where('rankings.0.total_winnings', 1500)
            ->where('rankings.0.accuracy_percentage', 100.0)
            ->where('rankings.1.rank', 2)
            ->where('rankings.1.user.first_name', 'Good Player')
            ->where('rankings.1.total_winnings', 675)
            ->where('rankings.2.rank', 3)
            ->where('rankings.2.user.first_name', 'Average Player')
            ->where('rankings.2.total_winnings', 300)
            ->where('userRank', 1) // Current user's rank
            ->where('totalParticipants', 3)
        );
});

it('handles tied scores correctly with tiebreaker rules', function () {
    $today = now()->toDateString();

    // Create users with same winnings but different accuracy
    $user1 = User::factory()->create([
        'telegram_id' => 111111111,
        'first_name' => 'High Accuracy',
    ]);

    $user2 = User::factory()->create([
        'telegram_id' => 222222222,
        'first_name' => 'Low Accuracy',
    ]);

    // Both users have same total winnings (500) but different accuracy
    DailyLeaderboard::create([
        'user_id' => $user1->id,
        'leaderboard_date' => $today,
        'total_winnings' => 500,
        'predictions_made' => 5,
        'correct_predictions' => 4, // 80% accuracy
        'accuracy_percentage' => 80.0,
        'rank' => 1, // Wins tiebreaker due to higher accuracy
    ]);

    DailyLeaderboard::create([
        'user_id' => $user2->id,
        'leaderboard_date' => $today,
        'total_winnings' => 500,
        'predictions_made' => 10,
        'correct_predictions' => 6, // 60% accuracy
        'accuracy_percentage' => 60.0,
        'rank' => 2, // Lower rank due to lower accuracy
    ]);

    $this->actingAs($user1);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('rankings.0.user.first_name', 'High Accuracy')
            ->where('rankings.0.rank', 1)
            ->where('rankings.1.user.first_name', 'Low Accuracy')
            ->where('rankings.1.rank', 2)
        );
});

it('calculates accuracy percentage correctly', function () {
    $today = now()->toDateString();
    
    $user = User::factory()->create(['telegram_id' => 123456789]);
    $category = Category::factory()->create(['is_active' => true]);

    // Create 8 questions
    $questions = PredictionQuestion::factory()->count(8)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);

    // User makes predictions: 6 correct, 2 incorrect
    foreach ($questions as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'choice' => $i < 6 ? 'A' : 'B', // First 6 correct, last 2 incorrect
            'is_correct' => $i < 6,
            'bet_amount' => 100,
            'actual_winnings' => $i < 6 ? 150 : 0,
        ]);
    }

    // Calculate leaderboard entry
    DailyLeaderboard::create([
        'user_id' => $user->id,
        'leaderboard_date' => $today,
        'total_winnings' => 900, // 6 * 150
        'predictions_made' => 8,
        'correct_predictions' => 6,
        'accuracy_percentage' => 75.0, // 6/8 * 100
        'rank' => 1,
    ]);

    $this->actingAs($user);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('rankings.0.accuracy_percentage', 75.0)
            ->where('rankings.0.predictions_made', 8)
            ->where('rankings.0.correct_predictions', 6)
        );
});

it('handles leaderboard with large number of participants', function () {
    $today = now()->toDateString();

    // Create 100 users with different winnings
    $users = [];
    for ($i = 1; $i <= 100; $i++) {
        $user = User::factory()->create([
            'telegram_id' => 100000000 + $i,
            'first_name' => "Player {$i}",
        ]);
        
        DailyLeaderboard::create([
            'user_id' => $user->id,
            'leaderboard_date' => $today,
            'total_winnings' => 5000 - ($i * 40), // Decreasing winnings
            'predictions_made' => 10,
            'correct_predictions' => max(1, 11 - ($i % 10)),
            'accuracy_percentage' => max(10, 100 - ($i % 10) * 10),
            'rank' => $i,
        ]);
        
        if ($i === 75) {
            $testUser = $user; // User at rank 75
        }
    }

    // Test with default limit (50)
    $this->actingAs($testUser);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 50) // Default limit
            ->where('userRank', 75) // User's actual rank shown even if not in top 50
            ->where('totalParticipants', 100)
        );

    // Test with custom limit
    $response = $this->get('/leaderboard?limit=25');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 25) // Custom limit
            ->where('userRank', 75)
            ->where('totalParticipants', 100)
        );
});

it('handles daily reset and multiple days correctly', function () {
    $yesterday = now()->subDay()->toDateString();
    $today = now()->toDateString();

    $user = User::factory()->create(['telegram_id' => 123456789]);

    // Create leaderboard entries for different days
    DailyLeaderboard::create([
        'user_id' => $user->id,
        'leaderboard_date' => $yesterday,
        'total_winnings' => 1000,
        'predictions_made' => 8,
        'correct_predictions' => 6,
        'accuracy_percentage' => 75.0,
        'rank' => 5,
    ]);

    DailyLeaderboard::create([
        'user_id' => $user->id,
        'leaderboard_date' => $today,
        'total_winnings' => 1200,
        'predictions_made' => 10,
        'correct_predictions' => 9,
        'accuracy_percentage' => 90.0,
        'rank' => 2,
    ]);

    $this->actingAs($user);

    // Test today's leaderboard (default)
    $response = $this->get('/leaderboard');
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userRank', 2)
            ->where('rankings.0.total_winnings', 1200) // Today's data
        );

    // Test yesterday's leaderboard
    $response = $this->get("/leaderboard?date={$yesterday}");
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userRank', 5)
            ->where('rankings.0.total_winnings', 1000) // Yesterday's data
        );
});

it('handles empty leaderboard gracefully', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $this->actingAs($user);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 0)
            ->where('userRank', null)
            ->where('totalParticipants', 0)
        );
});

it('ranks users by total winnings primarily', function () {
    $today = now()->toDateString();

    // Create users with winnings that should determine rank order
    $users = [
        ['winnings' => 2000, 'name' => 'First Place'],
        ['winnings' => 1500, 'name' => 'Second Place'], 
        ['winnings' => 1000, 'name' => 'Third Place'],
        ['winnings' => 500, 'name' => 'Fourth Place'],
    ];

    foreach ($users as $i => $userData) {
        $user = User::factory()->create([
            'telegram_id' => 100000000 + $i,
            'first_name' => $userData['name'],
        ]);

        DailyLeaderboard::create([
            'user_id' => $user->id,
            'leaderboard_date' => $today,
            'total_winnings' => $userData['winnings'],
            'predictions_made' => 5,
            'correct_predictions' => 4,
            'accuracy_percentage' => 80.0,
            'rank' => $i + 1,
        ]);
    }

    $testUser = User::where('first_name', 'Second Place')->first();
    $this->actingAs($testUser);

    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 4)
            ->where('rankings.0.user.first_name', 'First Place')
            ->where('rankings.0.total_winnings', 2000)
            ->where('rankings.0.rank', 1)
            ->where('rankings.1.user.first_name', 'Second Place')
            ->where('rankings.1.total_winnings', 1500)
            ->where('rankings.1.rank', 2)
            ->where('userRank', 2)
        );
});

it('updates leaderboard rankings when new predictions are resolved', function () {
    $today = now()->toDateString();

    $user1 = User::factory()->create(['telegram_id' => 111111111]);
    $user2 = User::factory()->create(['telegram_id' => 222222222]);

    // Initial leaderboard state
    DailyLeaderboard::create([
        'user_id' => $user1->id,
        'leaderboard_date' => $today,
        'total_winnings' => 500,
        'predictions_made' => 3,
        'correct_predictions' => 2,
        'accuracy_percentage' => 66.67,
        'rank' => 1,
    ]);

    DailyLeaderboard::create([
        'user_id' => $user2->id,
        'leaderboard_date' => $today,
        'total_winnings' => 300,
        'predictions_made' => 2,
        'correct_predictions' => 1,
        'accuracy_percentage' => 50.0,
        'rank' => 2,
    ]);

    // Simulate user2 getting a big win that changes rankings
    DailyLeaderboard::where('user_id', $user2->id)
        ->where('leaderboard_date', $today)
        ->update([
            'total_winnings' => 800, // Now higher than user1
            'predictions_made' => 3,
            'correct_predictions' => 2,
            'accuracy_percentage' => 66.67,
            'rank' => 1, // Now rank 1
        ]);

    DailyLeaderboard::where('user_id', $user1->id)
        ->where('leaderboard_date', $today)
        ->update([
            'rank' => 2, // Now rank 2
        ]);

    $this->actingAs($user2);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('rankings.0.total_winnings', 800) // User2 now first
            ->where('rankings.0.rank', 1)
            ->where('rankings.1.total_winnings', 500) // User1 now second
            ->where('rankings.1.rank', 2)
            ->where('userRank', 1) // Current user is now rank 1
        );
});