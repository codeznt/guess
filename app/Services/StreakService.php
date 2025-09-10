<?php

namespace App\Services;

use App\Models\User;
use App\Models\Prediction;
use App\Models\PredictionQuestion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class StreakService
{
    /**
     * Base multiplier for streak calculations.
     */
    public const BASE_MULTIPLIER = 1.0;

    /**
     * Maximum streak multiplier allowed.
     */
    public const MAX_MULTIPLIER = 2.0;

    /**
     * Multiplier increment per correct prediction.
     */
    public const MULTIPLIER_INCREMENT = 0.01;

    /**
     * Days to consider for streak calculation.
     */
    public const STREAK_WINDOW_DAYS = 30;

    /**
     * Cache TTL for streak data in seconds (10 minutes).
     */
    public const CACHE_TTL = 600;

    /**
     * Update user's streak based on a prediction result.
     */
    public function updateStreak(User $user, Prediction $prediction): array
    {
        return DB::transaction(function () use ($user, $prediction) {
            $previousStreak = $user->current_streak;
            $previousMultiplier = $user->streak_multiplier;

            if ($prediction->is_correct) {
                // Extend streak for correct prediction
                $newStreak = $user->current_streak + 1;
                $newMultiplier = $this->calculateStreakMultiplier($newStreak);
                
                // Update best streak if needed
                $bestStreak = max($user->best_streak, $newStreak);
            } else {
                // Reset streak for incorrect prediction
                $newStreak = 0;
                $newMultiplier = self::BASE_MULTIPLIER;
                $bestStreak = $user->best_streak;
            }

            // Update user streak data
            $user->update([
                'current_streak' => $newStreak,
                'streak_multiplier' => $newMultiplier,
                'best_streak' => $bestStreak,
                'last_prediction_date' => $prediction->created_at->toDateString(),
            ]);

            // Clear user's streak cache
            $this->clearUserStreakCache($user->id);

            Log::info('User streak updated', [
                'user_id' => $user->id,
                'prediction_id' => $prediction->id,
                'is_correct' => $prediction->is_correct,
                'previous_streak' => $previousStreak,
                'new_streak' => $newStreak,
                'previous_multiplier' => $previousMultiplier,
                'new_multiplier' => $newMultiplier,
            ]);

            return [
                'previous_streak' => $previousStreak,
                'new_streak' => $newStreak,
                'previous_multiplier' => $previousMultiplier,
                'new_multiplier' => $newMultiplier,
                'best_streak' => $bestStreak,
                'streak_broken' => $prediction->is_correct === false && $previousStreak > 0,
                'new_best' => $bestStreak > $user->best_streak,
            ];
        });
    }

    /**
     * Calculate streak multiplier based on current streak.
     */
    public function calculateStreakMultiplier(int $streak): float
    {
        if ($streak <= 0) {
            return self::BASE_MULTIPLIER;
        }

        $multiplier = self::BASE_MULTIPLIER + ($streak * self::MULTIPLIER_INCREMENT);
        
        return min($multiplier, self::MAX_MULTIPLIER);
    }

    /**
     * Get streak multiplier based on current streak (alias for calculateStreakMultiplier).
     */
    public function getStreakMultiplier(int $streak): float
    {
        return $this->calculateStreakMultiplier($streak);
    }

    /**
     * Get user's current streak information.
     */
    public function getUserStreakInfo(User $user): array
    {
        $cacheKey = "user_streak:{$user->id}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($user) {
            $recentPredictions = $this->getRecentPredictions($user, 10);
            
            return [
                'current_streak' => $user->current_streak,
                'streak_multiplier' => $user->streak_multiplier,
                'best_streak' => $user->best_streak,
                'last_prediction_date' => $user->last_prediction_date,
                'next_multiplier' => $this->calculateStreakMultiplier($user->current_streak + 1),
                'max_possible_multiplier' => self::MAX_MULTIPLIER,
                'streak_status' => $this->getStreakStatus($user->current_streak),
                'recent_predictions' => $recentPredictions,
                'streak_potential' => $this->calculateStreakPotential($user),
            ];
        });
    }

    /**
     * Get streak leaderboard.
     */
    public function getStreakLeaderboard(int $limit = 50): array
    {
        $cacheKey = "streak_leaderboard:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit) {
            $activeStreaks = User::where('current_streak', '>', 0)
                ->orderBy('current_streak', 'desc')
                ->orderBy('streak_multiplier', 'desc')
                ->limit($limit)
                ->get(['id', 'name', 'avatar', 'current_streak', 'streak_multiplier', 'best_streak'])
                ->map(function ($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'user' => $user,
                        'streak_status' => $this->getStreakStatus($user->current_streak),
                    ];
                });

            $bestStreaks = User::where('best_streak', '>', 0)
                ->orderBy('best_streak', 'desc')
                ->orderBy('current_streak', 'desc')
                ->limit($limit)
                ->get(['id', 'name', 'avatar', 'current_streak', 'streak_multiplier', 'best_streak'])
                ->map(function ($user, $index) {
                    return [
                        'rank' => $index + 1,
                        'user' => $user,
                        'streak_status' => $this->getStreakStatus($user->best_streak),
                    ];
                });

            return [
                'active_streaks' => $activeStreaks,
                'best_streaks' => $bestStreaks,
                'leaderboard_generated_at' => now(),
            ];
        });
    }

    /**
     * Get streak statistics for the system.
     */
    public function getStreakStatistics(): array
    {
        $cacheKey = 'system_streak_stats';
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $stats = User::selectRaw('
                COUNT(*) as total_users,
                COUNT(CASE WHEN current_streak > 0 THEN 1 END) as users_with_active_streaks,
                AVG(current_streak) as avg_current_streak,
                MAX(current_streak) as longest_current_streak,
                AVG(best_streak) as avg_best_streak,
                MAX(best_streak) as longest_ever_streak,
                COUNT(CASE WHEN current_streak >= 5 THEN 1 END) as users_with_5plus_streak,
                COUNT(CASE WHEN current_streak >= 10 THEN 1 END) as users_with_10plus_streak
            ')->first();

            $streakDistribution = User::selectRaw('
                CASE 
                    WHEN current_streak = 0 THEN "No Streak"
                    WHEN current_streak BETWEEN 1 AND 2 THEN "1-2"
                    WHEN current_streak BETWEEN 3 AND 4 THEN "3-4"
                    WHEN current_streak BETWEEN 5 AND 9 THEN "5-9"
                    WHEN current_streak BETWEEN 10 AND 19 THEN "10-19"
                    ELSE "20+"
                END as streak_range,
                COUNT(*) as user_count
            ')
            ->groupBy('streak_range')
            ->get()
            ->pluck('user_count', 'streak_range');

            $multiplierDistribution = User::selectRaw('
                CASE 
                    WHEN streak_multiplier = 1.0 THEN "1.0x (Base)"
                    WHEN streak_multiplier BETWEEN 1.1 AND 1.5 THEN "1.1-1.5x"
                    WHEN streak_multiplier BETWEEN 1.6 AND 2.0 THEN "1.6-2.0x"
                    WHEN streak_multiplier BETWEEN 2.1 AND 2.5 THEN "2.1-2.5x"
                    ELSE "2.6-3.0x"
                END as multiplier_range,
                COUNT(*) as user_count
            ')
            ->groupBy('multiplier_range')
            ->get()
            ->pluck('user_count', 'multiplier_range');

            return [
                'overview' => $stats,
                'streak_distribution' => $streakDistribution,
                'multiplier_distribution' => $multiplierDistribution,
                'generated_at' => now(),
            ];
        });
    }

    /**
     * Get users who lost their streak today.
     */
    public function getStreakBreakers(string $date = null): array
    {
        $date = $date ?? today()->toDateString();
        
        $streakBreakers = Prediction::whereDate('created_at', $date)
            ->where('is_correct', false)
            ->whereHas('user', function ($query) {
                $query->where('current_streak', 0)
                      ->where('best_streak', '>', 0);
            })
            ->with(['user:id,name,avatar,best_streak', 'question:id,title'])
            ->get()
            ->map(function ($prediction) {
                return [
                    'user' => $prediction->user,
                    'question' => $prediction->question,
                    'lost_streak' => $prediction->user->best_streak,
                    'prediction_time' => $prediction->created_at,
                ];
            });

        return [
            'date' => $date,
            'streak_breakers' => $streakBreakers,
            'total_streaks_broken' => $streakBreakers->count(),
        ];
    }

    /**
     * Check and fix streak inconsistencies.
     */
    public function validateAndFixStreaks(): array
    {
        $fixed = 0;
        $issues = [];

        $users = User::where('current_streak', '>', 0)->get();

        foreach ($users as $user) {
            try {
                $actualStreak = $this->recalculateUserStreak($user);
                
                if ($actualStreak !== $user->current_streak) {
                    $issues[] = [
                        'user_id' => $user->id,
                        'recorded_streak' => $user->current_streak,
                        'actual_streak' => $actualStreak,
                        'fixed' => true,
                    ];
                    
                    $user->update([
                        'current_streak' => $actualStreak,
                        'streak_multiplier' => $this->calculateStreakMultiplier($actualStreak),
                    ]);
                    
                    $fixed++;
                }
            } catch (\Exception $e) {
                $issues[] = [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'fixed' => false,
                ];
                
                Log::error('Failed to validate streak for user', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Streak validation completed', [
            'users_checked' => $users->count(),
            'issues_found' => count($issues),
            'streaks_fixed' => $fixed,
        ]);

        return [
            'users_checked' => $users->count(),
            'issues_found' => count($issues),
            'streaks_fixed' => $fixed,
            'issues' => $issues,
        ];
    }

    /**
     * Get streak history for a user.
     */
    public function getUserStreakHistory(User $user, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        $predictions = Prediction::where('user_id', $user->id)
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('is_correct')
            ->orderBy('created_at')
            ->get(['id', 'created_at', 'is_correct']);

        $streakHistory = [];
        $currentStreak = 0;
        
        foreach ($predictions as $prediction) {
            if ($prediction->is_correct) {
                $currentStreak++;
            } else {
                $currentStreak = 0;
            }
            
            $streakHistory[] = [
                'date' => $prediction->created_at->toDateString(),
                'is_correct' => $prediction->is_correct,
                'streak_after' => $currentStreak,
                'multiplier' => $this->calculateStreakMultiplier($currentStreak),
            ];
        }

        return [
            'period_days' => $days,
            'streak_history' => $streakHistory,
            'max_streak_in_period' => max(array_column($streakHistory, 'streak_after')),
            'total_predictions' => count($streakHistory),
        ];
    }

    /**
     * Get streak rewards and bonuses information.
     */
    public function getStreakRewards(): array
    {
        $rewards = [];
        
        for ($streak = 1; $streak <= 30; $streak += ($streak < 10 ? 1 : 5)) {
            $multiplier = $this->calculateStreakMultiplier($streak);
            $status = $this->getStreakStatus($streak);
            
            $rewards[] = [
                'streak_length' => $streak,
                'multiplier' => $multiplier,
                'status' => $status,
                'bonus_percentage' => round(($multiplier - 1) * 100, 1),
            ];
        }

        return [
            'base_multiplier' => self::BASE_MULTIPLIER,
            'max_multiplier' => self::MAX_MULTIPLIER,
            'increment' => self::MULTIPLIER_INCREMENT,
            'reward_tiers' => $rewards,
        ];
    }

    /**
     * Reset daily streaks for inactive users.
     */
    public function resetInactiveStreaks(int $inactiveDays = 2): array
    {
        $cutoffDate = now()->subDays($inactiveDays)->toDateString();
        
        $inactiveUsers = User::where('current_streak', '>', 0)
            ->where(function ($query) use ($cutoffDate) {
                $query->where('last_prediction_date', '<', $cutoffDate)
                      ->orWhereNull('last_prediction_date');
            })
            ->get();

        $resetCount = 0;
        
        foreach ($inactiveUsers as $user) {
            $user->update([
                'current_streak' => 0,
                'streak_multiplier' => self::BASE_MULTIPLIER,
            ]);
            
            $this->clearUserStreakCache($user->id);
            $resetCount++;
            
            Log::info('Streak reset for inactive user', [
                'user_id' => $user->id,
                'last_prediction_date' => $user->last_prediction_date,
                'days_inactive' => $user->last_prediction_date 
                    ? now()->diffInDays($user->last_prediction_date) 
                    : 'never',
            ]);
        }

        return [
            'inactive_days_threshold' => $inactiveDays,
            'users_reset' => $resetCount,
            'cutoff_date' => $cutoffDate,
        ];
    }

    /**
     * Get streak status description.
     */
    private function getStreakStatus(int $streak): string
    {
        return match (true) {
            $streak === 0 => 'No Streak',
            $streak === 1 => 'Getting Started',
            $streak <= 2 => 'Building Momentum',
            $streak <= 4 => 'On a Roll',
            $streak <= 9 => 'Hot Streak',
            $streak <= 19 => 'Streak Master',
            $streak <= 29 => 'Legendary',
            default => 'Unstoppable'
        };
    }

    /**
     * Recalculate user's streak based on recent predictions.
     */
    private function recalculateUserStreak(User $user): int
    {
        $recentPredictions = Prediction::where('user_id', $user->id)
            ->whereNotNull('is_correct')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get(['is_correct']);

        $streak = 0;
        
        foreach ($recentPredictions as $prediction) {
            if ($prediction->is_correct) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    /**
     * Get recent predictions for streak analysis.
     */
    private function getRecentPredictions(User $user, int $limit = 10): array
    {
        return Prediction::where('user_id', $user->id)
            ->whereNotNull('is_correct')
            ->with('question:id,title')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get(['id', 'question_id', 'is_correct', 'created_at'])
            ->map(function ($prediction) {
                return [
                    'question_title' => $prediction->question->title ?? 'Unknown',
                    'is_correct' => $prediction->is_correct,
                    'date' => $prediction->created_at->toDateString(),
                ];
            })
            ->toArray();
    }

    /**
     * Calculate streak potential based on user's history.
     */
    private function calculateStreakPotential(User $user): array
    {
        $recentAccuracy = $this->calculateRecentAccuracy($user, 10);
        $potentialStreak = $this->estimatePotentialStreak($recentAccuracy, $user->current_streak);
        
        return [
            'recent_accuracy' => $recentAccuracy,
            'potential_streak' => $potentialStreak,
            'confidence' => $this->calculateStreakConfidence($user),
        ];
    }

    /**
     * Calculate recent accuracy percentage.
     */
    private function calculateRecentAccuracy(User $user, int $predictions = 10): float
    {
        $recent = Prediction::where('user_id', $user->id)
            ->whereNotNull('is_correct')
            ->orderBy('created_at', 'desc')
            ->limit($predictions)
            ->get(['is_correct']);

        if ($recent->isEmpty()) {
            return 0;
        }

        $correct = $recent->where('is_correct', true)->count();
        return round(($correct / $recent->count()) * 100, 2);
    }

    /**
     * Estimate potential streak length.
     */
    private function estimatePotentialStreak(float $accuracy, int $currentStreak): int
    {
        if ($accuracy < 50) {
            return max(0, $currentStreak - 2);
        } elseif ($accuracy < 70) {
            return $currentStreak + 1;
        } elseif ($accuracy < 85) {
            return $currentStreak + 3;
        } else {
            return $currentStreak + 5;
        }
    }

    /**
     * Calculate confidence in streak continuation.
     */
    private function calculateStreakConfidence(User $user): string
    {
        $accuracy = $this->calculateRecentAccuracy($user, 10);
        
        return match (true) {
            $accuracy >= 80 => 'High',
            $accuracy >= 60 => 'Medium',
            $accuracy >= 40 => 'Low',
            default => 'Very Low'
        };
    }

    /**
     * Clear user's streak-related cache.
     */
    private function clearUserStreakCache(int $userId): void
    {
        Cache::forget("user_streak:{$userId}");
        Cache::forget('streak_leaderboard:50');
        Cache::forget('system_streak_stats');
    }
}