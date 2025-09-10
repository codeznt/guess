<?php

use App\Http\Controllers\AchievementsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\PredictionsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\QuestionsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

// Public achievement sharing (for shared links)
Route::get('/achievements/{achievement}', [AchievementsController::class, 'show'])
    ->name('achievements.show.public');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {
    
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');
    
    // Daily Questions
    Route::get('/questions/daily', [QuestionsController::class, 'daily'])
        ->name('questions.daily');
    
    // Predictions
    Route::post('/predictions', [PredictionsController::class, 'store'])
        ->name('predictions.store');
    
    Route::get('/predictions/{question}', [PredictionsController::class, 'show'])
        ->name('predictions.show');
    
    // Leaderboard
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])
        ->name('leaderboard.index');
    
    Route::get('/leaderboard/{period}', [LeaderboardController::class, 'period'])
        ->where('period', 'daily|weekly|monthly')
        ->name('leaderboard.period');
    
    // Profile
    Route::get('/profile', [ProfileController::class, 'show'])
        ->name('profile.show');
    
    // Achievements
    Route::get('/achievements', [AchievementsController::class, 'index'])
        ->name('achievements.index');
    
    Route::post('/achievements/share', [AchievementsController::class, 'share'])
        ->name('achievements.share');
    
});

// Settings and Auth routes
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
