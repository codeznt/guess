<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Models\DailyLeaderboard;
use App\Models\PredictionQuestion;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the user dashboard with daily questions and statistics.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Get daily questions with user predictions
        $dailyQuestions = $this->getDailyQuestions($user);

        // Get user statistics
        $userStats = $this->getUserStatistics($user);

        // Get today's activity statistics
        $todayStats = $this->getTodayStatistics($user, $dailyQuestions);

        // Get most recent achievement
        $recentAchievement = $this->getRecentAchievement($user);

        // Get user's leaderboard position for today
        $leaderboardPosition = $this->getLeaderboardPosition($user);

        // Get quick actions and recommendations
        $recommendations = $this->getRecommendations($user, $dailyQuestions, $todayStats);

        return Inertia::render('Dashboard', [
            'dailyQuestions' => $dailyQuestions->values(),
            'userStats' => $userStats,
            'todayStats' => $todayStats,
            'recentAchievement' => $recentAchievement,
            'leaderboardPosition' => $leaderboardPosition,
            'recommendations' => $recommendations,
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'daily_coins' => $user->daily_coins,
                'current_streak' => $user->current_streak,
                'best_streak' => $user->best_streak,
            ],
        ]);
    }

    /**
     * Get daily questions with user prediction status.
     */
    protected function getDailyQuestions($user)
    {
        return PredictionQuestion::with(['category', 'predictions' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->whereHas('category', function ($query) {
                $query->where('is_active', true);
            })
            ->where('status', 'active')
            ->where('resolution_time', '>', now())
            ->orderBy('resolution_time')
            ->orderBy('created_at')
            ->limit(20) // Limit to prevent overwhelming the dashboard
            ->get()
            ->map(function ($question) {
                $userPrediction = $question->predictions->first();

                return [
                    'id' => $question->id,
                    'title' => $question->title,
                    'option_a' => $question->option_a,
                    'option_b' => $question->option_b,
                    'resolution_time' => $question->resolution_time->toISOString(),
                    'is_resolved' => $question->status === 'resolved',
                    'correct_answer' => $question->correct_answer,
                    'category' => [
                        'id' => $question->category->id,
                        'name' => $question->category->name,
                        'icon' => $question->category->icon,
                        'color' => $question->category->color,
                    ],
                    'user_prediction' => $userPrediction ? [
                        'id' => $userPrediction->id,
                        'choice' => $userPrediction->choice,
                        'bet_amount' => $userPrediction->bet_amount,
                        'potential_winnings' => $userPrediction->potential_winnings,
                        'actual_winnings' => $userPrediction->actual_winnings,
                        'is_correct' => $userPrediction->is_correct,
                        'created_at' => $userPrediction->created_at->toISOString(),
                    ] : null,
                    'time_until_resolution' => $question->resolution_time->diffInMinutes(now()),
                    'is_ending_soon' => $question->resolution_time->diffInHours(now()) <= 2,
                ];
            });
    }

    /**
     * Get user statistics summary.
     */
    protected function getUserStatistics($user): array
    {
        $totalPredictions = $user->total_predictions ?? 0;
        $correctPredictions = $user->correct_predictions ?? 0;
        $accuracyPercentage = $totalPredictions > 0 
            ? round(($correctPredictions / $totalPredictions) * 100, 2)
            : 0.0;

        return [
            'dailyCoins' => $user->daily_coins,
            'totalPredictions' => $totalPredictions,
            'correctPredictions' => $correctPredictions,
            'accuracyPercentage' => $accuracyPercentage,
            'currentStreak' => $user->current_streak,
            'bestStreak' => $user->best_streak,
            'totalEarnings' => $user->predictions()
                ->whereNotNull('actual_winnings')
                ->sum('actual_winnings'),
            'level' => $this->calculateUserLevel($user),
        ];
    }

    /**
     * Get today's activity statistics.
     */
    protected function getTodayStatistics($user, $dailyQuestions): array
    {
        $predictionsToday = $user->predictions()
            ->whereDate('created_at', today())
            ->count();

        $coinsSpentToday = $user->predictions()
            ->whereDate('created_at', today())
            ->sum('bet_amount');

        $totalQuestions = $dailyQuestions->count();
        $questionsWithPredictions = $dailyQuestions->filter(fn($q) => $q['user_prediction'])->count();
        $questionsRemaining = $totalQuestions - $questionsWithPredictions;

        return [
            'predictionsToday' => $predictionsToday,
            'questionsRemaining' => $questionsRemaining,
            'coinsSpentToday' => $coinsSpentToday,
            'totalAvailableQuestions' => $totalQuestions,
            'completionPercentage' => $totalQuestions > 0 
                ? round(($questionsWithPredictions / $totalQuestions) * 100, 1)
                : 0,
        ];
    }

    /**
     * Get user's most recent achievement.
     */
    protected function getRecentAchievement($user): ?array
    {
        $achievement = Achievement::where('user_id', $user->id)
            ->orderBy('earned_at', 'desc')
            ->first();

        if (!$achievement) {
            return null;
        }

        return [
            'id' => $achievement->id,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'points_value' => $achievement->points_value,
            'achievement_type' => $achievement->achievement_type,
            'earned_at' => $achievement->earned_at->toISOString(),
            'is_recent' => $achievement->earned_at->diffInHours(now()) <= 24,
            'is_shareable' => $achievement->is_shareable,
        ];
    }

    /**
     * Get user's current leaderboard position.
     */
    protected function getLeaderboardPosition($user): ?array
    {
        $leaderboardEntry = DailyLeaderboard::where('user_id', $user->id)
            ->where('leaderboard_date', now()->toDateString())
            ->first();

        if (!$leaderboardEntry) {
            return null;
        }

        // Get total participants for context
        $totalParticipants = DailyLeaderboard::where('leaderboard_date', now()->toDateString())
            ->count();

        return [
            'rank' => $leaderboardEntry->rank,
            'totalWinnings' => $leaderboardEntry->total_winnings,
            'predictionsToday' => $leaderboardEntry->predictions_made,
            'accuracyPercentage' => $leaderboardEntry->accuracy_percentage,
            'totalParticipants' => $totalParticipants,
            'percentile' => $totalParticipants > 0 
                ? round(((($totalParticipants - $leaderboardEntry->rank) / $totalParticipants) * 100), 1)
                : 0,
            'isTopTen' => $leaderboardEntry->rank <= 10,
        ];
    }

    /**
     * Get personalized recommendations and quick actions.
     */
    protected function getRecommendations($user, $dailyQuestions, $todayStats): array
    {
        $recommendations = [];

        // No predictions made today
        if ($todayStats['predictionsToday'] === 0 && $dailyQuestions->count() > 0) {
            $recommendations[] = [
                'type' => 'start_predicting',
                'title' => 'Start Your Daily Predictions',
                'description' => "Make your first prediction to maintain your {$user->current_streak}-day streak",
                'action' => [
                    'text' => 'View Questions',
                    'url' => '/questions/daily',
                ],
                'priority' => 'high',
            ];
        }

        // Low coins warning
        if ($user->daily_coins < 100) {
            $recommendations[] = [
                'type' => 'low_coins',
                'title' => 'Low Coin Balance',
                'description' => 'You have less than 100 coins remaining. Consider smaller bets.',
                'action' => null,
                'priority' => 'medium',
            ];
        }

        // Streak at risk
        if ($user->current_streak > 0 && $todayStats['predictionsToday'] === 0) {
            $hoursLeft = 24 - now()->hour;
            $recommendations[] = [
                'type' => 'streak_risk',
                'title' => 'Streak at Risk!',
                'description' => "Only {$hoursLeft} hours left to continue your {$user->current_streak}-day streak",
                'action' => [
                    'text' => 'Make Predictions',
                    'url' => '/questions/daily',
                ],
                'priority' => 'urgent',
            ];
        }

        // Questions ending soon
        $endingSoon = $dailyQuestions->filter(fn($q) => $q['is_ending_soon'] && !$q['user_prediction']);
        if ($endingSoon->count() > 0) {
            $recommendations[] = [
                'type' => 'ending_soon',
                'title' => 'Questions Ending Soon',
                'description' => "{$endingSoon->count()} questions close within 2 hours",
                'action' => [
                    'text' => 'Quick Predict',
                    'url' => '/questions/daily',
                ],
                'priority' => 'medium',
            ];
        }

        // Leaderboard climbing opportunity
        $leaderboardPos = $this->getLeaderboardPosition($user);
        if ($leaderboardPos && $leaderboardPos['rank'] > 10 && $todayStats['questionsRemaining'] > 0) {
            $recommendations[] = [
                'type' => 'leaderboard_climb',
                'title' => 'Climb the Leaderboard',
                'description' => "You're rank #{$leaderboardPos['rank']}. Make more predictions to climb higher!",
                'action' => [
                    'text' => 'View Leaderboard',
                    'url' => '/leaderboard',
                ],
                'priority' => 'low',
            ];
        }

        // Sort by priority
        $priorityOrder = ['urgent' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($recommendations, fn($a, $b) => $priorityOrder[$a['priority']] <=> $priorityOrder[$b['priority']]);

        return array_slice($recommendations, 0, 3); // Limit to top 3
    }

    /**
     * Calculate user level based on achievements and activity.
     */
    protected function calculateUserLevel($user): int
    {
        $totalPoints = Achievement::where('user_id', $user->id)
            ->sum('points_value');
        
        // Level calculation: 1000 points per level
        return (int) floor($totalPoints / 1000) + 1;
    }
}