<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function __construct(
        protected StreakService $streakService
    ) {}

    /**
     * Display user profile with statistics and achievements.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();
        
        // Get user's achievements ordered by most recent first
        $achievements = Achievement::where('user_id', $user->id)
            ->orderBy('earned_at', 'desc')
            ->get()
            ->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'points_value' => $achievement->points_value,
                    'achievement_type' => $achievement->achievement_type,
                    'earned_at' => $achievement->earned_at->toISOString(),
                    'earned' => true,
                ];
            });

        // Calculate accuracy percentage
        $totalPredictions = $user->total_predictions ?? 0;
        $correctPredictions = $user->correct_predictions ?? 0;
        $accuracyPercentage = $totalPredictions > 0 
            ? round(($correctPredictions / $totalPredictions) * 100, 1)
            : 0.0;

        // Calculate total earnings from prediction history
        $totalEarnings = $user->predictions()
            ->whereNotNull('actual_winnings')
            ->sum('actual_winnings');

        // Calculate total wagered
        $totalWagered = $user->predictions()->sum('bet_amount');

        // Build user statistics
        $userStats = [
            'user' => [
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'telegram_id' => $user->telegram_id,
            ],
            'dailyCoins' => $user->daily_coins,
            'totalPredictions' => $totalPredictions,
            'correctPredictions' => $correctPredictions,
            'accuracyPercentage' => $accuracyPercentage,
            'currentStreak' => $user->current_streak,
            'bestStreak' => $user->best_streak,
            'totalEarnings' => $totalEarnings,
            'totalWagered' => $totalWagered,
            'netProfit' => $totalEarnings - $totalWagered,
            'achievements' => $achievements->values(),
        ];

        // Calculate streak information and milestones
        $streakInfo = [
            'currentStreak' => $user->current_streak,
            'bestStreak' => $user->best_streak,
            'streakMultiplier' => $this->streakService->getStreakMultiplier($user->current_streak),
            'nextMilestone' => $this->getNextStreakMilestone($user->current_streak),
            'milestones' => $this->getStreakMilestones($user->current_streak, $user->best_streak),
        ];

        // Get category-wise statistics
        $categoryStats = $user->predictions()
            ->with('question.category')
            ->get()
            ->groupBy('question.category.name')
            ->map(function ($predictions, $categoryName) {
                $total = $predictions->count();
                $correct = $predictions->where('is_correct', true)->count();
                
                return [
                    'category' => $categoryName,
                    'predictions' => $total,
                    'correct' => $correct,
                    'accuracy' => $total > 0 ? round(($correct / $total) * 100, 1) : 0,
                ];
            })
            ->values();

        // Calculate additional profile metrics
        $profileMetrics = [
            'totalPoints' => $achievements->sum('points_value'),
            'userLevel' => $this->calculateUserLevel($achievements->sum('points_value')),
            'predictionsToday' => $user->predictions()
                ->whereDate('created_at', today())
                ->count(),
            'lastPredictionDate' => $user->predictions()
                ->latest()
                ->first()
                ?->created_at
                ?->toISOString(),
        ];

        return Inertia::render('Profile/Stats', [
            'user' => array_merge($userStats['user'], [
                'daily_coins' => $user->daily_coins,
                'current_streak' => $user->current_streak,
                'best_streak' => $user->best_streak,
                'total_points' => $profileMetrics['totalPoints'],
                'total_winnings' => $totalEarnings,
                'total_wagered' => $totalWagered,
                'predictions_made' => $totalPredictions,
                'correct_predictions' => $correctPredictions,
            ]),
            'userStats' => $userStats,
            'streakInfo' => $streakInfo,
            'categoryStats' => $categoryStats,
            'profileMetrics' => $profileMetrics,
            'achievements' => $achievements->values(),
        ]);
    }

    /**
     * Get the next streak milestone.
     */
    protected function getNextStreakMilestone(int $currentStreak): int
    {
        $milestones = [3, 5, 7, 10, 15, 20, 25, 30, 50, 75, 100];
        
        foreach ($milestones as $milestone) {
            if ($milestone > $currentStreak) {
                return $milestone;
            }
        }
        
        // If beyond all predefined milestones, next milestone is next multiple of 25
        return ((int) floor($currentStreak / 25) + 1) * 25;
    }

    /**
     * Get streak milestones with achievement status.
     */
    protected function getStreakMilestones(int $currentStreak, int $bestStreak): array
    {
        $milestones = [
            ['days' => 3, 'title' => 'First Steps', 'bonus' => 1.5, 'emoji' => '🌱'],
            ['days' => 7, 'title' => 'Weekly Warrior', 'bonus' => 2.0, 'emoji' => '⚡'],
            ['days' => 14, 'title' => 'Fortnight Fighter', 'bonus' => 2.5, 'emoji' => '🔥'],
            ['days' => 30, 'title' => 'Monthly Master', 'bonus' => 3.0, 'emoji' => '👑'],
            ['days' => 50, 'title' => 'Consistency King', 'bonus' => 3.5, 'emoji' => '🏆'],
            ['days' => 100, 'title' => 'Legendary Predictor', 'bonus' => 4.0, 'emoji' => '💎'],
        ];

        return collect($milestones)->map(function ($milestone) use ($currentStreak, $bestStreak) {
            return array_merge($milestone, [
                'achieved' => $bestStreak >= $milestone['days'],
                'current' => $currentStreak >= $milestone['days'],
                'isNext' => $currentStreak < $milestone['days'] && 
                          ($currentStreak >= ($milestone['days'] - 7)), // Within 7 days of milestone
            ]);
        })->toArray();
    }

    /**
     * Calculate user level based on total points.
     */
    protected function calculateUserLevel(int $totalPoints): int
    {
        // Level calculation: 1000 points per level
        return (int) floor($totalPoints / 1000) + 1;
    }
}