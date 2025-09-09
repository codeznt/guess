<?php

namespace App\Services;

use App\Models\User;
use App\Models\DailyLeaderboard;
use App\Models\Prediction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class LeaderboardService
{
    /**
     * Cache key prefix for leaderboard data.
     */
    public const CACHE_PREFIX = 'leaderboard';

    /**
     * Cache TTL for leaderboard data in seconds (5 minutes).
     */
    public const CACHE_TTL = 300;

    /**
     * Default number of users to show on leaderboard.
     */
    public const DEFAULT_LIMIT = 50;

    /**
     * Maximum number of users that can be requested.
     */
    public const MAX_LIMIT = 200;

    /**
     * Get daily leaderboard for a specific date.
     */
    public function getDailyLeaderboard(string $date = null, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $date = $date ?? today()->toDateString();
        $limit = min($limit, self::MAX_LIMIT);
        
        $cacheKey = self::CACHE_PREFIX . ":daily:{$date}:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($date, $limit) {
            return DailyLeaderboard::where('date', $date)
                ->with('user:id,name,avatar')
                ->orderBy('rank')
                ->limit($limit)
                ->get();
        });
    }

    /**
     * Calculate and update daily leaderboard rankings.
     */
    public function calculateDailyLeaderboard(string $date = null): array
    {
        $date = $date ?? today()->toDateString();
        
        return DB::transaction(function () use ($date) {
            // Clear existing leaderboard for the date
            DailyLeaderboard::where('date', $date)->delete();
            
            // Get user statistics for the date
            $userStats = $this->getUserDailyStats($date);
            
            if ($userStats->isEmpty()) {
                Log::info('No daily statistics found for leaderboard calculation', ['date' => $date]);
                return ['users_ranked' => 0, 'date' => $date];
            }

            // Calculate rankings
            $rankings = $this->calculateRankings($userStats);
            
            // Batch insert leaderboard entries
            $leaderboardEntries = [];
            foreach ($rankings as $rank => $stats) {
                $leaderboardEntries[] = [
                    'user_id' => $stats->user_id,
                    'date' => $date,
                    'rank' => $rank + 1,
                    'total_predictions' => $stats->total_predictions,
                    'correct_predictions' => $stats->correct_predictions,
                    'total_winnings' => $stats->total_winnings,
                    'net_profit' => $stats->net_profit,
                    'accuracy_percentage' => $stats->accuracy_percentage,
                    'score' => $stats->score,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            
            DailyLeaderboard::insert($leaderboardEntries);
            
            // Clear cache for this date
            $this->clearDailyCache($date);
            
            Log::info('Daily leaderboard calculated', [
                'date' => $date,
                'users_ranked' => count($rankings),
                'top_score' => $rankings[0]->score ?? 0,
            ]);
            
            return [
                'users_ranked' => count($rankings),
                'date' => $date,
                'top_score' => $rankings[0]->score ?? 0,
            ];
        });
    }

    /**
     * Get weekly leaderboard aggregated from daily data.
     */
    public function getWeeklyLeaderboard(string $startDate = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $startDate = $startDate ?? now()->startOfWeek()->toDateString();
        $endDate = Carbon::parse($startDate)->addDays(6)->toDateString();
        $limit = min($limit, self::MAX_LIMIT);
        
        $cacheKey = self::CACHE_PREFIX . ":weekly:{$startDate}:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate, $endDate, $limit) {
            $weeklyStats = DB::table('daily_leaderboards')
                ->join('users', 'daily_leaderboards.user_id', '=', 'users.id')
                ->whereBetween('daily_leaderboards.date', [$startDate, $endDate])
                ->groupBy('users.id', 'users.name', 'users.avatar')
                ->select([
                    'users.id as user_id',
                    'users.name',
                    'users.avatar',
                    DB::raw('SUM(daily_leaderboards.total_predictions) as total_predictions'),
                    DB::raw('SUM(daily_leaderboards.correct_predictions) as correct_predictions'),
                    DB::raw('SUM(daily_leaderboards.total_winnings) as total_winnings'),
                    DB::raw('SUM(daily_leaderboards.net_profit) as net_profit'),
                    DB::raw('AVG(daily_leaderboards.accuracy_percentage) as avg_accuracy'),
                    DB::raw('SUM(daily_leaderboards.score) as total_score'),
                    DB::raw('COUNT(DISTINCT daily_leaderboards.date) as days_active'),
                ])
                ->havingRaw('total_predictions > 0')
                ->orderBy('total_score', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($stats, $index) {
                    $stats->rank = $index + 1;
                    $stats->avg_accuracy = round($stats->avg_accuracy, 2);
                    return $stats;
                });

            return [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'rankings' => $weeklyStats,
                'total_users' => $weeklyStats->count(),
            ];
        });
    }

    /**
     * Get monthly leaderboard aggregated from daily data.
     */
    public function getMonthlyLeaderboard(string $month = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $month = $month ?? now()->format('Y-m');
        $startDate = Carbon::parse($month . '-01')->toDateString();
        $endDate = Carbon::parse($month . '-01')->endOfMonth()->toDateString();
        $limit = min($limit, self::MAX_LIMIT);
        
        $cacheKey = self::CACHE_PREFIX . ":monthly:{$month}:{$limit}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate, $endDate, $limit, $month) {
            $monthlyStats = DB::table('daily_leaderboards')
                ->join('users', 'daily_leaderboards.user_id', '=', 'users.id')
                ->whereBetween('daily_leaderboards.date', [$startDate, $endDate])
                ->groupBy('users.id', 'users.name', 'users.avatar')
                ->select([
                    'users.id as user_id',
                    'users.name',
                    'users.avatar',
                    DB::raw('SUM(daily_leaderboards.total_predictions) as total_predictions'),
                    DB::raw('SUM(daily_leaderboards.correct_predictions) as correct_predictions'),
                    DB::raw('SUM(daily_leaderboards.total_winnings) as total_winnings'),
                    DB::raw('SUM(daily_leaderboards.net_profit) as net_profit'),
                    DB::raw('AVG(daily_leaderboards.accuracy_percentage) as avg_accuracy'),
                    DB::raw('SUM(daily_leaderboards.score) as total_score'),
                    DB::raw('COUNT(DISTINCT daily_leaderboards.date) as days_active'),
                    DB::raw('MIN(daily_leaderboards.rank) as best_daily_rank'),
                ])
                ->havingRaw('total_predictions > 0')
                ->orderBy('total_score', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($stats, $index) {
                    $stats->rank = $index + 1;
                    $stats->avg_accuracy = round($stats->avg_accuracy, 2);
                    return $stats;
                });

            return [
                'period' => ['month' => $month, 'start' => $startDate, 'end' => $endDate],
                'rankings' => $monthlyStats,
                'total_users' => $monthlyStats->count(),
            ];
        });
    }

    /**
     * Get user's leaderboard position and surrounding users.
     */
    public function getUserLeaderboardPosition(User $user, string $date = null, int $context = 5): array
    {
        $date = $date ?? today()->toDateString();
        
        $userEntry = DailyLeaderboard::where('date', $date)
            ->where('user_id', $user->id)
            ->first();

        if (!$userEntry) {
            return [
                'user_rank' => null,
                'user_stats' => null,
                'surrounding_users' => [],
                'total_users' => $this->getTotalUsersOnLeaderboard($date),
            ];
        }

        $surroundingUsers = DailyLeaderboard::where('date', $date)
            ->with('user:id,name,avatar')
            ->where('rank', '>=', max(1, $userEntry->rank - $context))
            ->where('rank', '<=', $userEntry->rank + $context)
            ->orderBy('rank')
            ->get();

        return [
            'user_rank' => $userEntry->rank,
            'user_stats' => $userEntry,
            'surrounding_users' => $surroundingUsers,
            'total_users' => $this->getTotalUsersOnLeaderboard($date),
        ];
    }

    /**
     * Get leaderboard statistics for a date range.
     */
    public function getLeaderboardStats(string $startDate = null, string $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7)->toDateString();
        $endDate = $endDate ?? today()->toDateString();
        
        $cacheKey = self::CACHE_PREFIX . ":stats:{$startDate}:{$endDate}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($startDate, $endDate) {
            $stats = DB::table('daily_leaderboards')
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw('
                    COUNT(DISTINCT user_id) as unique_participants,
                    COUNT(DISTINCT date) as active_days,
                    AVG(total_predictions) as avg_predictions_per_user,
                    AVG(accuracy_percentage) as avg_accuracy,
                    MAX(score) as highest_score,
                    AVG(score) as avg_score,
                    SUM(total_predictions) as total_predictions,
                    SUM(correct_predictions) as total_correct,
                    SUM(total_winnings) as total_winnings_paid
                ')
                ->first();

            $topPerformers = DB::table('daily_leaderboards')
                ->join('users', 'daily_leaderboards.user_id', '=', 'users.id')
                ->whereBetween('daily_leaderboards.date', [$startDate, $endDate])
                ->groupBy('users.id', 'users.name')
                ->select([
                    'users.id',
                    'users.name',
                    DB::raw('COUNT(*) as appearances'),
                    DB::raw('AVG(daily_leaderboards.rank) as avg_rank'),
                    DB::raw('MIN(daily_leaderboards.rank) as best_rank'),
                    DB::raw('SUM(daily_leaderboards.score) as total_score'),
                ])
                ->orderBy('total_score', 'desc')
                ->limit(10)
                ->get();

            return [
                'period' => ['start' => $startDate, 'end' => $endDate],
                'overview' => $stats,
                'top_performers' => $topPerformers,
            ];
        });
    }

    /**
     * Get user's leaderboard history.
     */
    public function getUserLeaderboardHistory(User $user, int $days = 30): Collection
    {
        $startDate = now()->subDays($days)->toDateString();
        
        return DailyLeaderboard::where('user_id', $user->id)
            ->where('date', '>=', $startDate)
            ->orderBy('date', 'desc')
            ->get();
    }

    /**
     * Get trending users (improving ranks).
     */
    public function getTrendingUsers(int $days = 7, int $limit = 20): array
    {
        $startDate = now()->subDays($days)->toDateString();
        
        $trendingUsers = DB::table('daily_leaderboards as dl1')
            ->join('daily_leaderboards as dl2', function ($join) use ($days) {
                $join->on('dl1.user_id', '=', 'dl2.user_id')
                     ->where('dl2.date', '=', now()->subDays($days)->toDateString());
            })
            ->join('users', 'dl1.user_id', '=', 'users.id')
            ->where('dl1.date', today()->toDateString())
            ->select([
                'users.id',
                'users.name',
                'users.avatar',
                'dl1.rank as current_rank',
                'dl2.rank as previous_rank',
                'dl1.score as current_score',
                'dl2.score as previous_score',
                DB::raw('(dl2.rank - dl1.rank) as rank_improvement'),
                DB::raw('(dl1.score - dl2.score) as score_improvement'),
            ])
            ->having('rank_improvement', '>', 0)
            ->orderBy('rank_improvement', 'desc')
            ->limit($limit)
            ->get();

        return [
            'period_days' => $days,
            'trending_users' => $trendingUsers,
            'total_trending' => $trendingUsers->count(),
        ];
    }

    /**
     * Calculate user score based on performance metrics.
     */
    public function calculateUserScore(object $stats): float
    {
        $baseScore = 0;
        
        // Points for predictions made (encourages participation)
        $baseScore += $stats->total_predictions * 10;
        
        // Bonus points for correct predictions
        $baseScore += $stats->correct_predictions * 50;
        
        // Accuracy bonus (exponential reward for higher accuracy)
        if ($stats->total_predictions > 0) {
            $accuracyBonus = pow($stats->accuracy_percentage / 100, 2) * 200;
            $baseScore += $accuracyBonus;
        }
        
        // Net profit bonus (rewards good betting decisions)
        if ($stats->net_profit > 0) {
            $baseScore += sqrt($stats->net_profit) * 5;
        }
        
        // Activity bonus for consistent participation
        if ($stats->total_predictions >= 5) {
            $baseScore += 100; // Consistency bonus
        }
        
        return round($baseScore, 2);
    }

    /**
     * Recalculate leaderboard for multiple dates.
     */
    public function bulkRecalculateLeaderboard(array $dates): array
    {
        $results = [];
        
        foreach ($dates as $date) {
            try {
                $result = $this->calculateDailyLeaderboard($date);
                $results[$date] = $result;
            } catch (\Exception $e) {
                Log::error('Failed to calculate leaderboard for date', [
                    'date' => $date,
                    'error' => $e->getMessage(),
                ]);
                
                $results[$date] = [
                    'error' => $e->getMessage(),
                    'users_ranked' => 0,
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get user daily statistics for leaderboard calculation.
     */
    private function getUserDailyStats(string $date): Collection
    {
        return DB::table('predictions')
            ->join('users', 'predictions.user_id', '=', 'users.id')
            ->whereDate('predictions.created_at', $date)
            ->groupBy('users.id')
            ->select([
                'users.id as user_id',
                DB::raw('COUNT(*) as total_predictions'),
                DB::raw('SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('SUM(predictions.bet_amount) as total_wagered'),
                DB::raw('SUM(COALESCE(predictions.actual_winnings, 0)) as total_winnings'),
                DB::raw('(SUM(COALESCE(predictions.actual_winnings, 0)) - SUM(predictions.bet_amount)) as net_profit'),
            ])
            ->get()
            ->map(function ($stats) {
                $stats->accuracy_percentage = $stats->total_predictions > 0 
                    ? round(($stats->correct_predictions / $stats->total_predictions) * 100, 2) 
                    : 0;
                $stats->score = $this->calculateUserScore($stats);
                
                return $stats;
            });
    }

    /**
     * Calculate rankings based on user statistics.
     */
    private function calculateRankings(Collection $userStats): array
    {
        return $userStats
            ->sortByDesc('score')
            ->values()
            ->toArray();
    }

    /**
     * Get total number of users on leaderboard for a date.
     */
    private function getTotalUsersOnLeaderboard(string $date): int
    {
        return DailyLeaderboard::where('date', $date)->count();
    }

    /**
     * Clear cache for a specific date.
     */
    private function clearDailyCache(string $date): void
    {
        $patterns = [
            self::CACHE_PREFIX . ":daily:{$date}:*",
            self::CACHE_PREFIX . ":stats:*",
        ];
        
        foreach ($patterns as $pattern) {
            // Laravel doesn't have a direct way to clear cache by pattern
            // This would need to be implemented based on your cache driver
            Cache::forget($pattern);
        }
    }
}