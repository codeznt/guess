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

    // Generate leaderboard from predictions
    DailyLeaderboard::generateDailyLeaderboard($today);

    // Test leaderboard retrieval
    $this->actingAs($user1);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 3)
            ->where('rankings.0.rank', 1)
            ->where('rankings.0.first_name', 'Top Performer')
            ->where('rankings.0.total_winnings', 1500)
            ->where('rankings.0.accuracy_percentage', '100.00')
            ->where('rankings.1.rank', 2)
            ->where('rankings.1.first_name', 'Good Player')
            ->where('rankings.1.total_winnings', 675)
            ->where('rankings.2.rank', 3)
            ->where('rankings.2.first_name', 'Average Player')
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

    $category = Category::factory()->create(['is_active' => true]);
    
    // User1: 5 predictions, 4 correct, 500 total winnings (100 per win)
    $questions1 = PredictionQuestion::factory()->count(5)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);
    
    foreach ($questions1 as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user1->id,
            'question_id' => $question->id,
            'choice' => $i < 4 ? 'A' : 'B', // 4 correct, 1 incorrect
            'is_correct' => $i < 4,
            'bet_amount' => 50,
            'actual_winnings' => $i < 4 ? 125 : 0, // 500 total winnings
        ]);
    }

    // User2: 10 predictions, 6 correct, 500 total winnings (lower accuracy)
    $questions2 = PredictionQuestion::factory()->count(10)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);
    
    foreach ($questions2 as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user2->id,
            'question_id' => $question->id,
            'choice' => $i < 6 ? 'A' : 'B', // 6 correct, 4 incorrect
            'is_correct' => $i < 6,
            'bet_amount' => 25,
            'actual_winnings' => $i < 6 ? 83.33 : 0, // ~500 total winnings
        ]);
    }

    // Generate leaderboard from predictions
    DailyLeaderboard::generateDailyLeaderboard($today);

    $this->actingAs($user1);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('rankings.0.first_name', 'High Accuracy')
            ->where('rankings.0.rank', 1)
            ->where('rankings.1.first_name', 'Low Accuracy')
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
            'is_correct' => $i < 6 ? true : false, // Explicitly set boolean values
            'bet_amount' => 100,
            'actual_winnings' => $i < 6 ? 150 : 0,
            'created_at' => $today,
            'updated_at' => $today,
        ]);
    }

    // Generate leaderboard from predictions for today
    DailyLeaderboard::generateDailyLeaderboard($today);

    $this->actingAs($user);
    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('rankings.0.accuracy_percentage', '75.00')
            ->where('rankings.0.predictions_made', 8)
            ->where('rankings.0.correct_predictions', 6)
        );
});

it('handles leaderboard with large number of participants', function () {
    $today = now()->toDateString();

    // Create 100 users with different winnings
    $category = Category::factory()->create(['is_active' => true]);
    $testUser = null;
    
    for ($i = 1; $i <= 100; $i++) {
        $user = User::factory()->create([
            'telegram_id' => 100000000 + $i,
            'first_name' => "Player {$i}",
        ]);
        
        // Create predictions for each user with decreasing winnings
        $questions = PredictionQuestion::factory()->count(10)->create([
            'category_id' => $category->id,
            'status' => 'resolved',
            'correct_answer' => 'A',
        ]);
        
        $correctPredictions = max(1, 11 - ($i % 10));
        $winningsPerCorrect = (5000 - ($i * 40)) / $correctPredictions;
        
        foreach ($questions as $j => $question) {
            Prediction::factory()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'choice' => $j < $correctPredictions ? 'A' : 'B',
                'is_correct' => $j < $correctPredictions,
                'bet_amount' => 50,
                'actual_winnings' => $j < $correctPredictions ? $winningsPerCorrect : 0,
            ]);
        }
        
        if ($i === 75) {
            $testUser = $user; // User at rank 75
        }
    }
    
    // Generate leaderboard from predictions
    DailyLeaderboard::generateDailyLeaderboard($today);

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

    $category = Category::factory()->create(['is_active' => true]);
    
    // Create predictions for yesterday
    $yesterdayQuestions = PredictionQuestion::factory()->count(8)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
        'created_at' => $yesterday,
    ]);
    
    foreach ($yesterdayQuestions as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'choice' => $i < 6 ? 'A' : 'B', // 6 correct, 2 incorrect
            'is_correct' => $i < 6,
            'bet_amount' => 100,
            'actual_winnings' => $i < 6 ? 166.67 : 0, // ~1000 total
            'created_at' => $yesterday,
        ]);
    }
    
    // Create predictions for today
    $todayQuestions = PredictionQuestion::factory()->count(10)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
        'created_at' => $today,
    ]);
    
    foreach ($todayQuestions as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'choice' => $i < 9 ? 'A' : 'B', // 9 correct, 1 incorrect
            'is_correct' => $i < 9,
            'bet_amount' => 100,
            'actual_winnings' => $i < 9 ? ($i === 8 ? 133.37 : 133.33) : 0, // Exactly 1200 total
            'created_at' => $today,
        ]);
    }
    
    // Generate leaderboards for both days
    DailyLeaderboard::generateDailyLeaderboard($yesterday);
    DailyLeaderboard::generateDailyLeaderboard($today);

    $this->actingAs($user);

    // Test today's leaderboard (default)
    $response = $this->get('/leaderboard');
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userRank', 1) // Only user with predictions today
            ->where('rankings.0.total_winnings', 1200) // Today's data
        );

    // Test yesterday's leaderboard
    $response = $this->get("/leaderboard?date={$yesterday}");
    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('userRank', 1) // Only user with predictions yesterday
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

    $category = Category::factory()->create(['is_active' => true]);
    
    foreach ($users as $i => $userData) {
        $user = User::factory()->create([
            'telegram_id' => 100000000 + $i,
            'first_name' => $userData['name'],
        ]);

        // Create predictions that result in the specified winnings
        $questions = PredictionQuestion::factory()->count(5)->create([
            'category_id' => $category->id,
            'status' => 'resolved',
            'correct_answer' => 'A',
        ]);
        
        $winningsPerCorrect = $userData['winnings'] / 4; // 4 correct predictions
        
        foreach ($questions as $j => $question) {
            Prediction::factory()->create([
                'user_id' => $user->id,
                'question_id' => $question->id,
                'choice' => $j < 4 ? 'A' : 'B', // 4 correct, 1 incorrect
                'is_correct' => $j < 4,
                'bet_amount' => 50,
                'actual_winnings' => $j < 4 ? $winningsPerCorrect : 0,
            ]);
        }
    }
    
    // Generate leaderboard from predictions
    DailyLeaderboard::generateDailyLeaderboard($today);

    $testUser = User::where('first_name', 'Second Place')->first();
    $this->actingAs($testUser);

    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->has('rankings', 4)
            ->where('rankings.0.first_name', 'First Place')
            ->where('rankings.0.total_winnings', 2000)
            ->where('rankings.0.rank', 1)
            ->where('rankings.1.first_name', 'Second Place')
            ->where('rankings.1.total_winnings', 1500)
            ->where('rankings.1.rank', 2)
            ->where('userRank', 2)
        );
});

it('updates leaderboard rankings when new predictions are resolved', function () {
    $today = now()->toDateString();

    $user1 = User::factory()->create(['telegram_id' => 111111111]);
    $user2 = User::factory()->create(['telegram_id' => 222222222]);

    $category = Category::factory()->create(['is_active' => true]);
    
    // Initial predictions for user1 (500 winnings)
    $questions1 = PredictionQuestion::factory()->count(3)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);
    
    foreach ($questions1 as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user1->id,
            'question_id' => $question->id,
            'choice' => $i < 2 ? 'A' : 'B', // 2 correct, 1 incorrect
            'is_correct' => $i < 2,
            'bet_amount' => 100,
            'actual_winnings' => $i < 2 ? 250 : 0, // 500 total
        ]);
    }
    
    // Initial predictions for user2 (300 winnings)
    $questions2 = PredictionQuestion::factory()->count(2)->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);
    
    foreach ($questions2 as $i => $question) {
        Prediction::factory()->create([
            'user_id' => $user2->id,
            'question_id' => $question->id,
            'choice' => $i < 1 ? 'A' : 'B', // 1 correct, 1 incorrect
            'is_correct' => $i < 1,
            'bet_amount' => 100,
            'actual_winnings' => $i < 1 ? 300 : 0, // 300 total
        ]);
    }
    
    // Generate initial leaderboard
    DailyLeaderboard::generateDailyLeaderboard($today);
    
    // Add another winning prediction for user2 to change rankings
    $newQuestion = PredictionQuestion::factory()->create([
        'category_id' => $category->id,
        'status' => 'resolved',
        'correct_answer' => 'A',
    ]);
    
    Prediction::factory()->create([
        'user_id' => $user2->id,
        'question_id' => $newQuestion->id,
        'choice' => 'A',
        'is_correct' => true,
        'bet_amount' => 100,
        'actual_winnings' => 500, // Big win to overtake user1
    ]);
    
    // Regenerate leaderboard with new prediction
    DailyLeaderboard::generateDailyLeaderboard($today);

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