<?php

require_once 'vendor/autoload.php';

use App\Models\User;
use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\DailyLeaderboard;
use Illuminate\Support\Facades\DB;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$today = now()->toDateString();
echo "Today: $today\n";

// Clear existing data
DB::table('predictions')->delete();
DB::table('daily_leaderboards')->delete();
DB::table('users')->delete();

$user = User::factory()->create(['telegram_id' => 123456789]);
$category = Category::factory()->create(['is_active' => true]);

// Create 8 questions
$questions = PredictionQuestion::factory()->count(8)->create([
    'category_id' => $category->id,
    'status' => 'resolved',
    'correct_answer' => 'A',
]);

echo "Created " . count($questions) . " questions\n";

// User makes predictions: 6 correct, 2 incorrect
foreach ($questions as $i => $question) {
    $prediction = Prediction::factory()->create([
        'user_id' => $user->id,
        'question_id' => $question->id,
        'choice' => $i < 6 ? 'A' : 'B', // First 6 correct, last 2 incorrect
        'is_correct' => $i < 6 ? true : false,
        'bet_amount' => 100,
        'actual_winnings' => $i < 6 ? 150 : 0,
        'created_at' => $today,
        'updated_at' => $today,
    ]);
    echo "Created prediction {$i}: choice=" . ($i < 6 ? 'A' : 'B') . ", is_correct=" . ($i < 6 ? 'true' : 'false') . "\n";
}

echo "\nPredictions in database:\n";
$predictions = DB::table('predictions')->get();
foreach ($predictions as $pred) {
    echo "ID: {$pred->id}, is_correct: {$pred->is_correct}, created_at: {$pred->created_at}\n";
}

echo "\nGenerating leaderboard for date: $today\n";
$count = DailyLeaderboard::generateDailyLeaderboard($today);
echo "Generated $count leaderboard entries\n";

echo "\nLeaderboard entries:\n";
$leaderboard = DB::table('daily_leaderboards')->get();
foreach ($leaderboard as $entry) {
    echo "User: {$entry->user_id}, accuracy: {$entry->accuracy_percentage}, predictions: {$entry->predictions_made}, correct: {$entry->correct_predictions}\n";
}

echo "\nDirect query for user stats:\n";
$userStats = DB::table('predictions')
    ->join('users', 'predictions.user_id', '=', 'users.id')
    ->whereDate('predictions.created_at', $today)
    ->whereNotNull('predictions.is_correct')
    ->select([
        'users.id as user_id',
        DB::raw('COUNT(predictions.id) as predictions_made'),
        DB::raw('SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
        DB::raw('CASE 
            WHEN COUNT(predictions.id) > 0 
            THEN ROUND((SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(predictions.id)) * 100, 2)
            ELSE 0 
        END as accuracy_percentage')
    ])
    ->groupBy('users.id')
    ->get();

foreach ($userStats as $stat) {
    echo "User: {$stat->user_id}, predictions: {$stat->predictions_made}, correct: {$stat->correct_predictions}, accuracy: {$stat->accuracy_percentage}\n";
}
