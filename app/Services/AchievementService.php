<?php

namespace App\Services;

use App\Models\User;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Models\Prediction;
use App\Models\PredictionQuestion;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AchievementService
{
    /**
     * Achievement types and their configurations.
     */
    public const ACHIEVEMENT_TYPES = [
        'prediction_count' => [
            'name' => 'Prediction Milestones',
            'description' => 'Achievements for making predictions',
            'icon' => '🎯',
        ],
        'accuracy' => [
            'name' => 'Accuracy Masters',
            'description' => 'Achievements for prediction accuracy',
            'icon' => '🎲',
        ],
        'streak' => [
            'name' => 'Streak Champions',
            'description' => 'Achievements for consecutive correct predictions',
            'icon' => '🔥',
        ],
        'winnings' => [
            'name' => 'Big Winners',
            'description' => 'Achievements for earning coins',
            'icon' => '💰',
        ],
        'participation' => [
            'name' => 'Active Players',
            'description' => 'Achievements for consistent participation',
            'icon' => '⭐',
        ],
        'social' => [
            'name' => 'Social Butterflies',
            'description' => 'Achievements for social interactions',
            'icon' => '🤝',
        ],
        'special' => [
            'name' => 'Special Events',
            'description' => 'Limited time and special achievements',
            'icon' => '🏆',
        ],
    ];

    /**
     * Predefined achievements configuration.
     */
    public const PREDEFINED_ACHIEVEMENTS = [
        // Prediction Count Achievements
        [
            'slug' => 'first_prediction',
            'type' => 'prediction_count',
            'name' => 'First Steps',
            'description' => 'Make your first prediction',
            'requirement_type' => 'count',
            'requirement_value' => 1,
            'reward_coins' => 50,
            'rarity' => 'common',
        ],
        [
            'slug' => 'prediction_10',
            'type' => 'prediction_count',
            'name' => 'Getting Started',
            'description' => 'Make 10 predictions',
            'requirement_type' => 'count',
            'requirement_value' => 10,
            'reward_coins' => 100,
            'rarity' => 'common',
        ],
        [
            'slug' => 'prediction_100',
            'type' => 'prediction_count',
            'name' => 'Dedicated Player',
            'description' => 'Make 100 predictions',
            'requirement_type' => 'count',
            'requirement_value' => 100,
            'reward_coins' => 500,
            'rarity' => 'uncommon',
        ],
        [
            'slug' => 'prediction_1000',
            'type' => 'prediction_count',
            'name' => 'Prediction Master',
            'description' => 'Make 1000 predictions',
            'requirement_type' => 'count',
            'requirement_value' => 1000,
            'reward_coins' => 2000,
            'rarity' => 'rare',
        ],

        // Accuracy Achievements
        [
            'slug' => 'accuracy_70',
            'type' => 'accuracy',
            'name' => 'Sharp Eye',
            'description' => 'Achieve 70% accuracy over 20 predictions',
            'requirement_type' => 'percentage',
            'requirement_value' => 70,
            'requirement_minimum' => 20,
            'reward_coins' => 200,
            'rarity' => 'uncommon',
        ],
        [
            'slug' => 'accuracy_80',
            'type' => 'accuracy',
            'name' => 'Expert Predictor',
            'description' => 'Achieve 80% accuracy over 50 predictions',
            'requirement_type' => 'percentage',
            'requirement_value' => 80,
            'requirement_minimum' => 50,
            'reward_coins' => 500,
            'rarity' => 'rare',
        ],
        [
            'slug' => 'accuracy_90',
            'type' => 'accuracy',
            'name' => 'Oracle',
            'description' => 'Achieve 90% accuracy over 100 predictions',
            'requirement_type' => 'percentage',
            'requirement_value' => 90,
            'requirement_minimum' => 100,
            'reward_coins' => 1000,
            'rarity' => 'legendary',
        ],

        // Streak Achievements
        [
            'slug' => 'streak_5',
            'type' => 'streak',
            'name' => 'Hot Streak',
            'description' => 'Get 5 correct predictions in a row',
            'requirement_type' => 'streak',
            'requirement_value' => 5,
            'reward_coins' => 150,
            'rarity' => 'common',
        ],
        [
            'slug' => 'streak_10',
            'type' => 'streak',
            'name' => 'On Fire',
            'description' => 'Get 10 correct predictions in a row',
            'requirement_type' => 'streak',
            'requirement_value' => 10,
            'reward_coins' => 400,
            'rarity' => 'uncommon',
        ],
        [
            'slug' => 'streak_20',
            'type' => 'streak',
            'name' => 'Unstoppable',
            'description' => 'Get 20 correct predictions in a row',
            'requirement_type' => 'streak',
            'requirement_value' => 20,
            'reward_coins' => 1000,
            'rarity' => 'rare',
        ],

        // Winnings Achievements
        [
            'slug' => 'winnings_1000',
            'type' => 'winnings',
            'name' => 'First Thousand',
            'description' => 'Earn 1,000 coins in winnings',
            'requirement_type' => 'cumulative',
            'requirement_value' => 1000,
            'reward_coins' => 100,
            'rarity' => 'common',
        ],
        [
            'slug' => 'winnings_10000',
            'type' => 'winnings',
            'name' => 'Big Winner',
            'description' => 'Earn 10,000 coins in winnings',
            'requirement_type' => 'cumulative',
            'requirement_value' => 10000,
            'reward_coins' => 500,
            'rarity' => 'uncommon',
        ],
        [
            'slug' => 'winnings_100000',
            'type' => 'winnings',
            'name' => 'Jackpot King',
            'description' => 'Earn 100,000 coins in winnings',
            'requirement_type' => 'cumulative',
            'requirement_value' => 100000,
            'reward_coins' => 2000,
            'rarity' => 'legendary',
        ],

        // Participation Achievements
        [
            'slug' => 'daily_7',
            'type' => 'participation',
            'name' => 'Week Warrior',
            'description' => 'Make predictions on 7 consecutive days',
            'requirement_type' => 'consecutive_days',
            'requirement_value' => 7,
            'reward_coins' => 200,
            'rarity' => 'common',
        ],
        [
            'slug' => 'daily_30',
            'type' => 'participation',
            'name' => 'Monthly Champion',
            'description' => 'Make predictions on 30 consecutive days',
            'requirement_type' => 'consecutive_days',
            'requirement_value' => 30,
            'reward_coins' => 1000,
            'rarity' => 'rare',
        ],

        // Special Achievements
        [
            'slug' => 'perfect_day',
            'type' => 'special',
            'name' => 'Perfect Day',
            'description' => 'Get all predictions correct in a single day (minimum 5)',
            'requirement_type' => 'perfect_day',
            'requirement_value' => 5,
            'reward_coins' => 300,
            'rarity' => 'rare',
        ],
        [
            'slug' => 'comeback_king',
            'type' => 'special',
            'name' => 'Comeback King',
            'description' => 'Win 5 predictions in a row after losing 3 in a row',
            'requirement_type' => 'comeback',
            'requirement_value' => 5,
            'reward_coins' => 400,
            'rarity' => 'uncommon',
        ],
    ];

    /**
     * Check and award achievements for a user after a prediction.
     */
    public function checkAchievements(User $user, Prediction $prediction = null): array
    {
        return DB::transaction(function () use ($user, $prediction) {
            $newAchievements = [];
            $availableAchievements = Achievement::where('is_active', true)->get();

            foreach ($availableAchievements as $achievement) {
                if ($this->shouldAwardAchievement($user, $achievement, $prediction)) {
                    $newAchievements[] = $this->awardAchievement($user, $achievement);
                }
            }

            if (!empty($newAchievements)) {
                Log::info('Achievements awarded', [
                    'user_id' => $user->id,
                    'achievements' => array_column($newAchievements, 'slug'),
                    'total_coins_awarded' => array_sum(array_column($newAchievements, 'reward_coins')),
                ]);

                // Clear user's achievement cache
                $this->clearUserAchievementCache($user->id);
            }

            return $newAchievements;
        });
    }

    /**
     * Get user's achievements with progress.
     */
    public function getUserAchievements(User $user): array
    {
        $cacheKey = "user_achievements:{$user->id}";
        
        return Cache::remember($cacheKey, 600, function () use ($user) {
            $userAchievements = UserAchievement::where('user_id', $user->id)
                ->with('achievement')
                ->get()
                ->groupBy('achievement.type');

            $allAchievements = Achievement::where('is_active', true)
                ->get()
                ->groupBy('type');

            $achievementsByType = [];

            foreach (self::ACHIEVEMENT_TYPES as $type => $typeConfig) {
                $earned = $userAchievements[$type] ?? collect();
                $available = $allAchievements[$type] ?? collect();
                
                $achievements = $available->map(function ($achievement) use ($earned, $user) {
                    $userAchievement = $earned->firstWhere('achievement_id', $achievement->id);
                    $progress = $this->calculateAchievementProgress($user, $achievement);
                    
                    return [
                        'achievement' => $achievement,
                        'earned' => !is_null($userAchievement),
                        'earned_at' => $userAchievement?->created_at,
                        'progress' => $progress,
                    ];
                });

                $achievementsByType[$type] = [
                    'type_info' => $typeConfig,
                    'achievements' => $achievements,
                    'earned_count' => $earned->count(),
                    'total_count' => $available->count(),
                ];
            }

            $stats = $this->getUserAchievementStats($user);

            return [
                'achievements_by_type' => $achievementsByType,
                'stats' => $stats,
            ];
        });
    }

    /**
     * Get achievement leaderboard.
     */
    public function getAchievementLeaderboard(int $limit = 50): array
    {
        $cacheKey = "achievement_leaderboard:{$limit}";
        
        return Cache::remember($cacheKey, 300, function () use ($limit) {
            $leaderboard = DB::table('user_achievements')
                ->join('users', 'user_achievements.user_id', '=', 'users.id')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->groupBy('users.id', 'users.name', 'users.avatar')
                ->select([
                    'users.id',
                    'users.name',
                    'users.avatar',
                    DB::raw('COUNT(*) as total_achievements'),
                    DB::raw('SUM(achievements.reward_coins) as total_reward_coins'),
                    DB::raw('COUNT(CASE WHEN achievements.rarity = "legendary" THEN 1 END) as legendary_count'),
                    DB::raw('COUNT(CASE WHEN achievements.rarity = "rare" THEN 1 END) as rare_count'),
                    DB::raw('COUNT(CASE WHEN achievements.rarity = "uncommon" THEN 1 END) as uncommon_count'),
                    DB::raw('COUNT(CASE WHEN achievements.rarity = "common" THEN 1 END) as common_count'),
                ])
                ->orderByDesc('total_achievements')
                ->orderByDesc('total_reward_coins')
                ->limit($limit)
                ->get()
                ->map(function ($user, $index) {
                    $user->rank = $index + 1;
                    return $user;
                });

            return [
                'leaderboard' => $leaderboard,
                'generated_at' => now(),
            ];
        });
    }

    /**
     * Create system achievements if they don't exist.
     */
    public function createSystemAchievements(): array
    {
        $created = [];
        $skipped = [];

        foreach (self::PREDEFINED_ACHIEVEMENTS as $achievementData) {
            $existing = Achievement::where('slug', $achievementData['slug'])->first();
            
            if (!$existing) {
                $achievement = Achievement::create([
                    'slug' => $achievementData['slug'],
                    'type' => $achievementData['type'],
                    'name' => $achievementData['name'],
                    'description' => $achievementData['description'],
                    'icon' => self::ACHIEVEMENT_TYPES[$achievementData['type']]['icon'],
                    'requirement_type' => $achievementData['requirement_type'],
                    'requirement_value' => $achievementData['requirement_value'],
                    'requirement_minimum' => $achievementData['requirement_minimum'] ?? null,
                    'reward_coins' => $achievementData['reward_coins'],
                    'rarity' => $achievementData['rarity'],
                    'is_active' => true,
                ]);
                
                $created[] = $achievement->slug;
            } else {
                $skipped[] = $achievementData['slug'];
            }
        }

        Log::info('System achievements creation completed', [
            'created_count' => count($created),
            'skipped_count' => count($skipped),
            'created' => $created,
        ]);

        return [
            'created' => $created,
            'skipped' => $skipped,
        ];
    }

    /**
     * Get achievement statistics.
     */
    public function getAchievementStatistics(): array
    {
        $cacheKey = 'achievement_statistics';
        
        return Cache::remember($cacheKey, 600, function () {
            $totalAchievements = Achievement::where('is_active', true)->count();
            $totalAwarded = UserAchievement::count();
            $uniqueEarners = UserAchievement::distinct('user_id')->count('user_id');

            $rarityStats = Achievement::where('is_active', true)
                ->select('rarity', DB::raw('COUNT(*) as count'))
                ->groupBy('rarity')
                ->pluck('count', 'rarity');

            $typeStats = Achievement::where('is_active', true)
                ->select('type', DB::raw('COUNT(*) as count'))
                ->groupBy('type')
                ->pluck('count', 'type');

            $mostEarned = DB::table('user_achievements')
                ->join('achievements', 'user_achievements.achievement_id', '=', 'achievements.id')
                ->select('achievements.slug', 'achievements.name', DB::raw('COUNT(*) as earned_count'))
                ->groupBy('achievements.id', 'achievements.slug', 'achievements.name')
                ->orderByDesc('earned_count')
                ->limit(10)
                ->get();

            $rarest = DB::table('achievements')
                ->leftJoin('user_achievements', 'achievements.id', '=', 'user_achievements.achievement_id')
                ->select('achievements.slug', 'achievements.name', 'achievements.rarity', DB::raw('COUNT(user_achievements.id) as earned_count'))
                ->where('achievements.is_active', true)
                ->groupBy('achievements.id', 'achievements.slug', 'achievements.name', 'achievements.rarity')
                ->orderBy('earned_count')
                ->limit(10)
                ->get();

            return [
                'overview' => [
                    'total_achievements' => $totalAchievements,
                    'total_awarded' => $totalAwarded,
                    'unique_earners' => $uniqueEarners,
                    'average_per_user' => $uniqueEarners > 0 ? round($totalAwarded / $uniqueEarners, 2) : 0,
                ],
                'by_rarity' => $rarityStats,
                'by_type' => $typeStats,
                'most_earned' => $mostEarned,
                'rarest' => $rarest,
                'generated_at' => now(),
            ];
        });
    }

    /**
     * Get recent achievement awards.
     */
    public function getRecentAchievements(int $hours = 24, int $limit = 50): array
    {
        $since = now()->subHours($hours);
        
        $recentAwards = UserAchievement::with(['user:id,name,avatar', 'achievement'])
            ->where('created_at', '>=', $since)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($award) {
                return [
                    'user' => $award->user,
                    'achievement' => $award->achievement,
                    'awarded_at' => $award->created_at,
                    'time_ago' => $award->created_at->diffForHumans(),
                ];
            });

        return [
            'period_hours' => $hours,
            'recent_awards' => $recentAwards,
            'total_awards' => $recentAwards->count(),
        ];
    }

    /**
     * Check if user should be awarded an achievement.
     */
    private function shouldAwardAchievement(User $user, Achievement $achievement, Prediction $prediction = null): bool
    {
        // Check if user already has this achievement
        if (UserAchievement::where('user_id', $user->id)->where('achievement_id', $achievement->id)->exists()) {
            return false;
        }

        return match ($achievement->requirement_type) {
            'count' => $this->checkCountRequirement($user, $achievement),
            'percentage' => $this->checkPercentageRequirement($user, $achievement),
            'streak' => $this->checkStreakRequirement($user, $achievement),
            'cumulative' => $this->checkCumulativeRequirement($user, $achievement),
            'consecutive_days' => $this->checkConsecutiveDaysRequirement($user, $achievement),
            'perfect_day' => $this->checkPerfectDayRequirement($user, $achievement),
            'comeback' => $this->checkComebackRequirement($user, $achievement),
            default => false,
        };
    }

    /**
     * Award achievement to user.
     */
    private function awardAchievement(User $user, Achievement $achievement): array
    {
        $userAchievement = UserAchievement::create([
            'user_id' => $user->id,
            'achievement_id' => $achievement->id,
        ]);

        // Award coins if specified
        if ($achievement->reward_coins > 0) {
            $user->addWinnings($achievement->reward_coins);
        }

        return [
            'slug' => $achievement->slug,
            'name' => $achievement->name,
            'description' => $achievement->description,
            'reward_coins' => $achievement->reward_coins,
            'rarity' => $achievement->rarity,
            'awarded_at' => $userAchievement->created_at,
        ];
    }

    /**
     * Calculate achievement progress for a user.
     */
    private function calculateAchievementProgress(User $user, Achievement $achievement): array
    {
        $progress = match ($achievement->requirement_type) {
            'count' => $this->calculateCountProgress($user, $achievement),
            'percentage' => $this->calculatePercentageProgress($user, $achievement),
            'streak' => $this->calculateStreakProgress($user, $achievement),
            'cumulative' => $this->calculateCumulativeProgress($user, $achievement),
            'consecutive_days' => $this->calculateConsecutiveDaysProgress($user, $achievement),
            default => ['current' => 0, 'required' => $achievement->requirement_value, 'percentage' => 0],
        };

        $progress['percentage'] = $progress['required'] > 0 
            ? min(100, round(($progress['current'] / $progress['required']) * 100, 1))
            : 0;

        return $progress;
    }

    /**
     * Check count-based achievement requirement.
     */
    private function checkCountRequirement(User $user, Achievement $achievement): bool
    {
        $count = Prediction::where('user_id', $user->id)->count();
        return $count >= $achievement->requirement_value;
    }

    /**
     * Check percentage-based achievement requirement.
     */
    private function checkPercentageRequirement(User $user, Achievement $achievement): bool
    {
        $predictions = Prediction::where('user_id', $user->id)->whereNotNull('is_correct');
        
        if ($predictions->count() < ($achievement->requirement_minimum ?? 1)) {
            return false;
        }

        $accuracy = ($predictions->where('is_correct', true)->count() / $predictions->count()) * 100;
        return $accuracy >= $achievement->requirement_value;
    }

    /**
     * Check streak-based achievement requirement.
     */
    private function checkStreakRequirement(User $user, Achievement $achievement): bool
    {
        return $user->best_streak >= $achievement->requirement_value;
    }

    /**
     * Check cumulative achievement requirement.
     */
    private function checkCumulativeRequirement(User $user, Achievement $achievement): bool
    {
        $total = match ($achievement->type) {
            'winnings' => Prediction::where('user_id', $user->id)->sum('actual_winnings'),
            default => 0,
        };

        return $total >= $achievement->requirement_value;
    }

    /**
     * Check consecutive days requirement.
     */
    private function checkConsecutiveDaysRequirement(User $user, Achievement $achievement): bool
    {
        $consecutiveDays = $this->calculateConsecutivePredictionDays($user);
        return $consecutiveDays >= $achievement->requirement_value;
    }

    /**
     * Check perfect day requirement.
     */
    private function checkPerfectDayRequirement(User $user, Achievement $achievement): bool
    {
        return DB::table('predictions')
            ->where('user_id', $user->id)
            ->whereNotNull('is_correct')
            ->selectRaw('DATE(created_at) as prediction_date')
            ->selectRaw('COUNT(*) as total_predictions')
            ->selectRaw('SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions')
            ->groupBy('prediction_date')
            ->havingRaw('total_predictions >= ?', [$achievement->requirement_value])
            ->havingRaw('total_predictions = correct_predictions')
            ->exists();
    }

    /**
     * Check comeback requirement.
     */
    private function checkComebackRequirement(User $user, Achievement $achievement): bool
    {
        $recentPredictions = Prediction::where('user_id', $user->id)
            ->whereNotNull('is_correct')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->pluck('is_correct')
            ->reverse()
            ->values();

        $consecutiveLosses = 0;
        $consecutiveWins = 0;
        $foundLossStreak = false;

        foreach ($recentPredictions as $isCorrect) {
            if (!$isCorrect) {
                if (!$foundLossStreak) {
                    $consecutiveLosses++;
                    $consecutiveWins = 0;
                } else {
                    break;
                }
            } else {
                if ($consecutiveLosses >= 3) {
                    $foundLossStreak = true;
                }
                if ($foundLossStreak) {
                    $consecutiveWins++;
                } else {
                    $consecutiveLosses = 0;
                }
            }
        }

        return $consecutiveLosses >= 3 && $consecutiveWins >= $achievement->requirement_value;
    }

    /**
     * Calculate progress for count-based achievements.
     */
    private function calculateCountProgress(User $user, Achievement $achievement): array
    {
        $current = Prediction::where('user_id', $user->id)->count();
        
        return [
            'current' => $current,
            'required' => $achievement->requirement_value,
        ];
    }

    /**
     * Calculate progress for percentage-based achievements.
     */
    private function calculatePercentageProgress(User $user, Achievement $achievement): array
    {
        $predictions = Prediction::where('user_id', $user->id)->whereNotNull('is_correct');
        $total = $predictions->count();
        
        if ($total === 0) {
            return ['current' => 0, 'required' => $achievement->requirement_value];
        }

        $correct = $predictions->where('is_correct', true)->count();
        $accuracy = round(($correct / $total) * 100, 2);
        
        return [
            'current' => $accuracy,
            'required' => $achievement->requirement_value,
            'minimum_predictions' => $achievement->requirement_minimum ?? 1,
            'current_predictions' => $total,
        ];
    }

    /**
     * Calculate progress for streak-based achievements.
     */
    private function calculateStreakProgress(User $user, Achievement $achievement): array
    {
        return [
            'current' => $user->best_streak,
            'required' => $achievement->requirement_value,
        ];
    }

    /**
     * Calculate progress for cumulative achievements.
     */
    private function calculateCumulativeProgress(User $user, Achievement $achievement): array
    {
        $current = match ($achievement->type) {
            'winnings' => Prediction::where('user_id', $user->id)->sum('actual_winnings'),
            default => 0,
        };

        return [
            'current' => $current,
            'required' => $achievement->requirement_value,
        ];
    }

    /**
     * Calculate progress for consecutive days achievements.
     */
    private function calculateConsecutiveDaysProgress(User $user, Achievement $achievement): array
    {
        $consecutiveDays = $this->calculateConsecutivePredictionDays($user);
        
        return [
            'current' => $consecutiveDays,
            'required' => $achievement->requirement_value,
        ];
    }

    /**
     * Calculate consecutive prediction days for a user.
     */
    private function calculateConsecutivePredictionDays(User $user): int
    {
        $predictionDates = Prediction::where('user_id', $user->id)
            ->selectRaw('DATE(created_at) as prediction_date')
            ->distinct()
            ->orderByDesc('prediction_date')
            ->pluck('prediction_date')
            ->map(fn($date) => Carbon::parse($date));

        if ($predictionDates->isEmpty()) {
            return 0;
        }

        $consecutiveDays = 1;
        $currentDate = $predictionDates->first();

        foreach ($predictionDates->skip(1) as $date) {
            if ($currentDate->copy()->subDay()->equalTo($date)) {
                $consecutiveDays++;
                $currentDate = $date;
            } else {
                break;
            }
        }

        return $consecutiveDays;
    }

    /**
     * Get user achievement statistics.
     */
    private function getUserAchievementStats(User $user): array
    {
        $userAchievements = UserAchievement::where('user_id', $user->id)
            ->with('achievement')
            ->get();

        $totalEarned = $userAchievements->count();
        $totalCoins = $userAchievements->sum('achievement.reward_coins');
        
        $byRarity = $userAchievements->groupBy('achievement.rarity')
            ->map->count();

        $byType = $userAchievements->groupBy('achievement.type')
            ->map->count();

        return [
            'total_earned' => $totalEarned,
            'total_reward_coins' => $totalCoins,
            'by_rarity' => $byRarity,
            'by_type' => $byType,
            'latest_achievement' => $userAchievements->sortByDesc('created_at')->first()?->achievement,
        ];
    }

    /**
     * Clear user's achievement cache.
     */
    private function clearUserAchievementCache(int $userId): void
    {
        Cache::forget("user_achievements:{$userId}");
        Cache::forget('achievement_leaderboard:50');
        Cache::forget('achievement_statistics');
    }
}