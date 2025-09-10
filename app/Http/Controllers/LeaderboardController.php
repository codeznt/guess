<?php

namespace App\Http\Controllers;

use App\Models\DailyLeaderboard;
use App\Services\LeaderboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __construct(
        protected LeaderboardService $leaderboardService
    ) {}

    /**
     * Display the daily leaderboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        
        // Get query parameters
        $date = $request->query('date', now()->toDateString());
        $limit = min((int) $request->query('limit', 50), 100); // Max 100 entries
        
        // Get leaderboard entries for the specified date
        $query = DailyLeaderboard::with(['user'])
            ->where('leaderboard_date', $date)
            ->orderBy('rank')
            ->orderBy('total_winnings', 'desc');
        
        // Get total participants for this date
        $totalParticipants = $query->count();
        
        // Get top rankings (limited)
        $topRankings = $query->limit($limit)->get();
        
        // Find user's rank for this date
        $userRank = null;
        $userEntry = DailyLeaderboard::where('user_id', $user->id)
            ->where('leaderboard_date', $date)
            ->first();
            
        if ($userEntry) {
            $userRank = $userEntry->rank;
        }
        
        // Transform rankings data for frontend
        $rankings = $topRankings->map(function ($entry) use ($user) {
            return [
                'user_id' => $entry->user_id,
                'rank' => $entry->rank,
                'total_winnings' => $entry->total_winnings,
                'predictions_made' => $entry->predictions_made,
                'correct_predictions' => $entry->correct_predictions,
                'accuracy_percentage' => $entry->accuracy_percentage,
                'current_streak' => $entry->user->current_streak ?? 0,
                'user' => [
                    'id' => $entry->user->id,
                    'first_name' => $entry->user->first_name,
                    'last_name' => $entry->user->last_name,
                    'username' => $entry->user->username,
                    'telegram_id' => $entry->user->telegram_id,
                ],
                'is_current_user' => $entry->user_id === $user->id,
            ];
        })->values();

        // If user is not in top rankings but has an entry, get their details
        $userPosition = null;
        if ($userEntry && $userRank > $limit) {
            $userPosition = [
                'user_id' => $userEntry->user_id,
                'rank' => $userEntry->rank,
                'total_winnings' => $userEntry->total_winnings,
                'predictions_made' => $userEntry->predictions_made,
                'correct_predictions' => $userEntry->correct_predictions,
                'accuracy_percentage' => $userEntry->accuracy_percentage,
                'current_streak' => $user->current_streak ?? 0,
                'user' => [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'username' => $user->username,
                    'telegram_id' => $user->telegram_id,
                ],
                'is_current_user' => true,
            ];
        }

        // Calculate period statistics
        $periodStats = $this->calculatePeriodStats($date, $totalParticipants);
        
        // Meta information
        $meta = [
            'date' => $date,
            'lastUpdated' => now()->toISOString(),
            'refreshInterval' => 300, // 5 minutes in seconds
            'isToday' => $date === now()->toDateString(),
            'period' => $this->determinePeriod($request->query('period', 'daily')),
        ];

        return Inertia::render('Leaderboard/Daily', [
            'rankings' => $rankings,
            'userRank' => $userRank,
            'userPosition' => $userPosition,
            'totalParticipants' => $totalParticipants,
            'periodStats' => $periodStats,
            'meta' => $meta,
            'currentUserId' => $user->id,
        ]);
    }

    /**
     * Get leaderboard data for a specific period.
     */
    public function period(Request $request, string $period): Response
    {
        $user = $request->user();
        
        // Validate period
        if (!in_array($period, ['daily', 'weekly', 'monthly'])) {
            return redirect()->route('leaderboard.index')
                ->with('error', 'Invalid leaderboard period');
        }

        $limit = min((int) $request->query('limit', 50), 100);
        
        // Calculate date range based on period
        $dateRange = $this->getDateRangeForPeriod($period);
        
        // Get aggregated leaderboard data
        $leaderboardData = $this->leaderboardService
            ->getLeaderboardForPeriod($period, $dateRange['start'], $dateRange['end'], $limit);
        
        $meta = [
            'period' => $period,
            'dateRange' => $dateRange,
            'lastUpdated' => now()->toISOString(),
            'refreshInterval' => $period === 'daily' ? 300 : 3600, // 5 min for daily, 1 hour for others
        ];

        return Inertia::render('Leaderboard/Daily', [
            'rankings' => $leaderboardData['rankings'],
            'userRank' => $leaderboardData['userRank'],
            'userPosition' => $leaderboardData['userPosition'],
            'totalParticipants' => $leaderboardData['totalParticipants'],
            'periodStats' => $leaderboardData['stats'],
            'meta' => $meta,
            'currentUserId' => $user->id,
        ]);
    }

    /**
     * Calculate statistics for the current period.
     */
    protected function calculatePeriodStats(string $date, int $totalParticipants): array
    {
        $leaderboard = DailyLeaderboard::where('leaderboard_date', $date);
        
        return [
            'total_participants' => $totalParticipants,
            'total_winnings' => $leaderboard->sum('total_winnings'),
            'total_predictions' => $leaderboard->sum('predictions_made'),
            'average_accuracy' => $totalParticipants > 0 
                ? round($leaderboard->avg('accuracy_percentage'), 2)
                : 0,
            'highest_winnings' => $leaderboard->max('total_winnings') ?: 0,
            'highest_accuracy' => $leaderboard->max('accuracy_percentage') ?: 0,
        ];
    }

    /**
     * Determine the period type from request.
     */
    protected function determinePeriod(string $requestPeriod): string
    {
        $validPeriods = ['daily', 'weekly', 'monthly'];
        return in_array($requestPeriod, $validPeriods) ? $requestPeriod : 'daily';
    }

    /**
     * Get date range for a specific period.
     */
    protected function getDateRangeForPeriod(string $period): array
    {
        $now = now();
        
        return match ($period) {
            'weekly' => [
                'start' => $now->startOfWeek()->toDateString(),
                'end' => $now->endOfWeek()->toDateString(),
            ],
            'monthly' => [
                'start' => $now->startOfMonth()->toDateString(),
                'end' => $now->endOfMonth()->toDateString(),
            ],
            default => [
                'start' => $now->toDateString(),
                'end' => $now->toDateString(),
            ],
        };
    }
}