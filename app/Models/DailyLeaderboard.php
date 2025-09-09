<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class DailyLeaderboard extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'leaderboard_date',
        'total_winnings',
        'predictions_made',
        'correct_predictions',
        'accuracy_percentage',
        'rank',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'leaderboard_date' => 'date',
        'total_winnings' => 'integer',
        'predictions_made' => 'integer',
        'correct_predictions' => 'integer',
        'accuracy_percentage' => 'decimal:2',
        'rank' => 'integer',
    ];

    /**
     * Get the user that owns the leaderboard entry.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get leaderboard for a specific date.
     */
    public function scopeForDate($query, string $date)
    {
        return $query->where('leaderboard_date', $date);
    }

    /**
     * Scope to get today's leaderboard.
     */
    public function scopeToday($query)
    {
        return $query->where('leaderboard_date', today());
    }

    /**
     * Scope to get leaderboard ordered by rank.
     */
    public function scopeRanked($query)
    {
        return $query->orderBy('rank');
    }

    /**
     * Scope to get top N entries.
     */
    public function scopeTop($query, int $limit = 50)
    {
        return $query->orderBy('rank')->limit($limit);
    }

    /**
     * Scope to get leaderboard with user data.
     */
    public function scopeWithUser($query)
    {
        return $query->with(['user:id,telegram_id,first_name,last_name,username']);
    }

    /**
     * Generate daily leaderboard for a specific date.
     */
    public static function generateDailyLeaderboard(string $date = null): int
    {
        $date = $date ?? today()->toDateString();

        // Clear existing leaderboard for this date
        self::where('leaderboard_date', $date)->delete();

        // Get users who made predictions on this date
        $userStats = DB::table('predictions')
            ->join('users', 'predictions.user_id', '=', 'users.id')
            ->whereDate('predictions.created_at', $date)
            ->whereNotNull('predictions.is_correct') // Only resolved predictions
            ->select([
                'users.id as user_id',
                DB::raw('COALESCE(SUM(predictions.actual_winnings), 0) as total_winnings'),
                DB::raw('COUNT(predictions.id) as predictions_made'),
                DB::raw('SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('CASE 
                    WHEN COUNT(predictions.id) > 0 
                    THEN ROUND((SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) / COUNT(predictions.id)) * 100, 2)
                    ELSE 0 
                END as accuracy_percentage')
            ])
            ->groupBy('users.id')
            ->orderBy('total_winnings', 'desc')
            ->orderBy('accuracy_percentage', 'desc')
            ->orderBy('correct_predictions', 'desc')
            ->get();

        if ($userStats->isEmpty()) {
            return 0;
        }

        // Assign ranks and create leaderboard entries
        $rank = 1;
        $leaderboardEntries = [];
        $previousWinnings = null;
        $previousAccuracy = null;

        foreach ($userStats as $index => $stat) {
            // Handle ties - users with same winnings and accuracy get same rank
            if ($previousWinnings !== null && 
                ($stat->total_winnings != $previousWinnings || $stat->accuracy_percentage != $previousAccuracy)) {
                $rank = $index + 1;
            }

            $leaderboardEntries[] = [
                'user_id' => $stat->user_id,
                'leaderboard_date' => $date,
                'total_winnings' => $stat->total_winnings,
                'predictions_made' => $stat->predictions_made,
                'correct_predictions' => $stat->correct_predictions,
                'accuracy_percentage' => $stat->accuracy_percentage,
                'rank' => $rank,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $previousWinnings = $stat->total_winnings;
            $previousAccuracy = $stat->accuracy_percentage;
        }

        // Batch insert leaderboard entries
        self::insert($leaderboardEntries);

        return count($leaderboardEntries);
    }

    /**
     * Get leaderboard with pagination and user's rank.
     */
    public static function getLeaderboardWithUserRank(string $date = null, int $limit = 50, int $userId = null): array
    {
        $date = $date ?? today()->toDateString();

        // Get top entries
        $topEntries = self::forDate($date)
            ->withUser()
            ->ranked()
            ->limit($limit)
            ->get();

        // Get total participants count
        $totalParticipants = self::forDate($date)->count();

        // Get user's rank if user ID provided
        $userRank = null;
        if ($userId) {
            $userEntry = self::forDate($date)->where('user_id', $userId)->first();
            $userRank = $userEntry?->rank;
        }

        return [
            'rankings' => $topEntries,
            'user_rank' => $userRank,
            'total_participants' => $totalParticipants,
            'meta' => [
                'date' => $date,
                'last_updated' => self::forDate($date)->max('updated_at'),
            ],
        ];
    }

    /**
     * Get user's leaderboard history.
     */
    public static function getUserHistory(int $userId, int $days = 30): array
    {
        $history = self::where('user_id', $userId)
            ->where('leaderboard_date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('leaderboard_date', 'desc')
            ->get();

        $stats = [
            'total_days' => $history->count(),
            'best_rank' => $history->min('rank'),
            'average_rank' => $history->avg('rank'),
            'total_winnings' => $history->sum('total_winnings'),
            'average_accuracy' => $history->avg('accuracy_percentage'),
            'best_day_winnings' => $history->max('total_winnings'),
            'consistency_score' => self::calculateConsistencyScore($history),
        ];

        return [
            'history' => $history,
            'stats' => $stats,
        ];
    }

    /**
     * Calculate consistency score based on rank variations.
     */
    private static function calculateConsistencyScore($history): float
    {
        if ($history->count() < 2) {
            return 0.0;
        }

        $ranks = $history->pluck('rank');
        $mean = $ranks->avg();
        $variance = $ranks->sum(function ($rank) use ($mean) {
            return pow($rank - $mean, 2);
        }) / $ranks->count();

        // Lower variance = higher consistency (inverse relationship)
        $standardDeviation = sqrt($variance);
        
        // Normalize to 0-100 scale (lower std dev = higher score)
        return max(0, min(100, 100 - $standardDeviation));
    }

    /**
     * Get current leaderboard position trends.
     */
    public static function getPositionTrends(string $date = null, int $days = 7): array
    {
        $endDate = $date ?? today()->toDateString();
        $startDate = now()->parse($endDate)->subDays($days - 1)->toDateString();

        $trends = self::whereBetween('leaderboard_date', [$startDate, $endDate])
            ->withUser()
            ->orderBy('leaderboard_date')
            ->orderBy('rank')
            ->get()
            ->groupBy('user_id');

        $trendData = [];
        foreach ($trends as $userId => $userHistory) {
            $ranks = $userHistory->pluck('rank');
            $user = $userHistory->first()->user;

            $trendData[] = [
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'username' => $user->username,
                ],
                'current_rank' => $userHistory->last()->rank,
                'previous_rank' => $userHistory->count() > 1 ? $userHistory->slice(-2, 1)->first()->rank : null,
                'rank_change' => $userHistory->count() > 1 
                    ? $userHistory->slice(-2, 1)->first()->rank - $userHistory->last()->rank
                    : 0,
                'trend' => self::calculateTrend($ranks),
                'best_rank' => $ranks->min(),
                'worst_rank' => $ranks->max(),
            ];
        }

        return $trendData;
    }

    /**
     * Calculate trend direction based on rank history.
     */
    private static function calculateTrend($ranks): string
    {
        if ($ranks->count() < 2) {
            return 'stable';
        }

        $recent = $ranks->slice(-3); // Last 3 entries
        if ($recent->count() < 2) {
            return 'stable';
        }

        $first = $recent->first();
        $last = $recent->last();
        $difference = $first - $last; // Lower rank number = better position

        if ($difference > 2) {
            return 'improving'; // Rank number decreased (better position)
        } elseif ($difference < -2) {
            return 'declining'; // Rank number increased (worse position)
        } else {
            return 'stable';
        }
    }

    /**
     * Get the top performers for a date range.
     */
    public static function getTopPerformers(string $startDate, string $endDate, int $limit = 10): array
    {
        $performers = self::whereBetween('leaderboard_date', [$startDate, $endDate])
            ->withUser()
            ->select([
                'user_id',
                DB::raw('AVG(rank) as average_rank'),
                DB::raw('MIN(rank) as best_rank'),
                DB::raw('SUM(total_winnings) as total_winnings'),
                DB::raw('AVG(accuracy_percentage) as average_accuracy'),
                DB::raw('SUM(predictions_made) as total_predictions'),
                DB::raw('COUNT(*) as active_days'),
            ])
            ->groupBy('user_id')
            ->having('active_days', '>=', 3) // Must have at least 3 active days
            ->orderBy('average_rank', 'asc')
            ->orderBy('total_winnings', 'desc')
            ->limit($limit)
            ->with('user')
            ->get();

        return $performers;
    }

    /**
     * Check if user made the leaderboard for a specific date.
     */
    public static function userMadeLeaderboard(int $userId, string $date = null): bool
    {
        $date = $date ?? today()->toDateString();
        return self::forDate($date)->where('user_id', $userId)->exists();
    }

    /**
     * Get leaderboard statistics for a date.
     */
    public static function getLeaderboardStats(string $date = null): array
    {
        $date = $date ?? today()->toDateString();

        $stats = self::forDate($date)->select([
            DB::raw('COUNT(*) as total_participants'),
            DB::raw('AVG(total_winnings) as average_winnings'),
            DB::raw('MAX(total_winnings) as highest_winnings'),
            DB::raw('AVG(accuracy_percentage) as average_accuracy'),
            DB::raw('MAX(accuracy_percentage) as highest_accuracy'),
            DB::raw('SUM(predictions_made) as total_predictions'),
        ])->first();

        return [
            'date' => $date,
            'total_participants' => $stats->total_participants ?? 0,
            'average_winnings' => round($stats->average_winnings ?? 0, 2),
            'highest_winnings' => $stats->highest_winnings ?? 0,
            'average_accuracy' => round($stats->average_accuracy ?? 0, 2),
            'highest_accuracy' => round($stats->highest_accuracy ?? 0, 2),
            'total_predictions' => $stats->total_predictions ?? 0,
        ];
    }
}