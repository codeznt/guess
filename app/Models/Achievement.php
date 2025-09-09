<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'achievement_type',
        'title',
        'description',
        'icon',
        'points_value',
        'is_shareable',
        'earned_at',
        'shared_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'points_value' => 'integer',
        'is_shareable' => 'boolean',
        'earned_at' => 'datetime',
        'shared_at' => 'datetime',
    ];

    /**
     * Achievement types.
     */
    public const TYPE_FIRST_PREDICTION = 'first_prediction';
    public const TYPE_PERFECT_DAY = 'perfect_day';
    public const TYPE_STREAK_MILESTONE = 'streak_milestone';
    public const TYPE_BIG_WINNER = 'big_winner';
    public const TYPE_CONSISTENCY = 'consistency';
    public const TYPE_SOCIAL_BUTTERFLY = 'social_butterfly';
    public const TYPE_RISK_TAKER = 'risk_taker';
    public const TYPE_CONSERVATIVE = 'conservative';

    /**
     * Sharing platforms.
     */
    public const PLATFORM_TELEGRAM = 'telegram';
    public const PLATFORM_TWITTER = 'twitter';
    public const PLATFORM_FACEBOOK = 'facebook';
    public const PLATFORM_INSTAGRAM = 'instagram';

    /**
     * Get the user that owns the achievement.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get achievements by type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('achievement_type', $type);
    }

    /**
     * Scope to get shareable achievements.
     */
    public function scopeShareable($query)
    {
        return $query->where('is_shareable', true);
    }

    /**
     * Scope to get shared achievements.
     */
    public function scopeShared($query)
    {
        return $query->whereNotNull('shared_at');
    }

    /**
     * Scope to get unshared achievements.
     */
    public function scopeUnshared($query)
    {
        return $query->whereNull('shared_at');
    }

    /**
     * Scope to get recent achievements.
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('earned_at', '>=', now()->subDays($days));
    }

    /**
     * Scope to get achievements ordered by earned date.
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('earned_at', 'desc');
    }

    /**
     * Check if the achievement has been shared.
     */
    public function isShared(): bool
    {
        return !is_null($this->shared_at);
    }

    /**
     * Mark the achievement as shared.
     */
    public function markAsShared(): void
    {
        $this->update(['shared_at' => now()]);
    }

    /**
     * Get the time since the achievement was earned.
     */
    public function getTimeSinceEarnedAttribute(): string
    {
        return $this->earned_at->diffForHumans();
    }

    /**
     * Get share URL for the achievement.
     */
    public function getShareUrl(string $platform, string $customMessage = null): string
    {
        $message = $customMessage ?? $this->getDefaultShareMessage();
        $encodedMessage = urlencode($message);

        switch ($platform) {
            case self::PLATFORM_TELEGRAM:
                return "https://t.me/share/url?url=" . urlencode(config('app.url')) . "&text={$encodedMessage}";
            
            case self::PLATFORM_TWITTER:
                return "https://twitter.com/intent/tweet?text={$encodedMessage}&url=" . urlencode(config('app.url'));
            
            case self::PLATFORM_FACEBOOK:
                return "https://www.facebook.com/sharer/sharer.php?u=" . urlencode(config('app.url')) . "&quote={$encodedMessage}";
            
            case self::PLATFORM_INSTAGRAM:
                // Instagram doesn't support direct sharing URLs, return app URL
                return config('app.url');
            
            default:
                return config('app.url');
        }
    }

    /**
     * Get default share message for the achievement.
     */
    public function getDefaultShareMessage(): string
    {
        return "🏆 Just earned '{$this->title}' in the Social Prediction Game! {$this->description}";
    }

    /**
     * Create an achievement for a user.
     */
    public static function createForUser(int $userId, string $type, array $data = []): ?self
    {
        // Check if user already has this type of achievement
        $existingAchievement = self::where('user_id', $userId)
            ->where('achievement_type', $type)
            ->first();

        // For certain achievement types, allow multiples (e.g., streak milestones)
        if ($existingAchievement && !in_array($type, [self::TYPE_STREAK_MILESTONE, self::TYPE_BIG_WINNER])) {
            return $existingAchievement; // Don't create duplicate
        }

        $achievementData = self::getAchievementData($type, $data);
        
        if (!$achievementData) {
            return null;
        }

        return self::create([
            'user_id' => $userId,
            'achievement_type' => $type,
            'title' => $achievementData['title'],
            'description' => $achievementData['description'],
            'icon' => $achievementData['icon'],
            'points_value' => $achievementData['points_value'],
            'is_shareable' => $achievementData['is_shareable'] ?? true,
            'earned_at' => now(),
        ]);
    }

    /**
     * Get achievement data based on type.
     */
    private static function getAchievementData(string $type, array $data = []): ?array
    {
        switch ($type) {
            case self::TYPE_FIRST_PREDICTION:
                return [
                    'title' => 'First Steps',
                    'description' => 'Made your first prediction! Welcome to the game!',
                    'icon' => 'play-circle',
                    'points_value' => 10,
                ];

            case self::TYPE_PERFECT_DAY:
                return [
                    'title' => 'Perfect Day',
                    'description' => 'Achieved 100% accuracy for a day with ' . ($data['predictions'] ?? 'multiple') . ' predictions!',
                    'icon' => 'trophy',
                    'points_value' => 100,
                ];

            case self::TYPE_STREAK_MILESTONE:
                $streak = $data['streak'] ?? 5;
                return [
                    'title' => "{$streak}-Day Streak",
                    'description' => "Achieved {$streak} consecutive correct predictions! 🔥",
                    'icon' => $streak >= 25 ? 'crown' : 'fire',
                    'points_value' => $streak * 10,
                ];

            case self::TYPE_BIG_WINNER:
                $winnings = $data['winnings'] ?? 1000;
                return [
                    'title' => 'Big Winner',
                    'description' => "Won {$winnings} coins in a single day! 💰",
                    'icon' => 'coins',
                    'points_value' => min($winnings / 10, 500), // Cap at 500 points
                ];

            case self::TYPE_CONSISTENCY:
                $days = $data['days'] ?? 7;
                return [
                    'title' => 'Consistency Champion',
                    'description' => "Made predictions for {$days} consecutive days!",
                    'icon' => 'calendar-check',
                    'points_value' => $days * 5,
                ];

            case self::TYPE_SOCIAL_BUTTERFLY:
                return [
                    'title' => 'Social Butterfly',
                    'description' => 'Shared your first achievement with friends!',
                    'icon' => 'share',
                    'points_value' => 25,
                ];

            case self::TYPE_RISK_TAKER:
                return [
                    'title' => 'High Roller',
                    'description' => 'Consistently betting high amounts! Fortune favors the bold!',
                    'icon' => 'dice',
                    'points_value' => 75,
                ];

            case self::TYPE_CONSERVATIVE:
                return [
                    'title' => 'Steady Player',
                    'description' => 'Consistent with small, smart bets! Slow and steady wins the race!',
                    'icon' => 'shield-check',
                    'points_value' => 50,
                ];

            default:
                return null;
        }
    }

    /**
     * Check and award streak milestone achievements.
     */
    public static function checkStreakMilestone(User $user): ?self
    {
        $streak = $user->current_streak;
        $milestones = [5, 10, 15, 25, 50, 75, 100];

        if (!in_array($streak, $milestones)) {
            return null;
        }

        // Check if user already has this specific milestone
        $existingMilestone = self::where('user_id', $user->id)
            ->where('achievement_type', self::TYPE_STREAK_MILESTONE)
            ->where('title', "{$streak}-Day Streak")
            ->first();

        if ($existingMilestone) {
            return $existingMilestone;
        }

        return self::createForUser($user->id, self::TYPE_STREAK_MILESTONE, ['streak' => $streak]);
    }

    /**
     * Check and award perfect day achievement.
     */
    public static function checkPerfectDay(User $user, string $date = null): ?self
    {
        $date = $date ?? today()->toDateString();

        $dailyStats = Prediction::getDailyStats($user->id, $date);
        
        if ($dailyStats['resolved_predictions'] >= 3 && $dailyStats['accuracy_percentage'] == 100.0) {
            return self::createForUser($user->id, self::TYPE_PERFECT_DAY, [
                'predictions' => $dailyStats['resolved_predictions']
            ]);
        }

        return null;
    }

    /**
     * Check and award big winner achievement.
     */
    public static function checkBigWinner(User $user, int $winnings, string $date = null): ?self
    {
        if ($winnings >= 1000) {
            return self::createForUser($user->id, self::TYPE_BIG_WINNER, ['winnings' => $winnings]);
        }

        return null;
    }

    /**
     * Award first prediction achievement.
     */
    public static function awardFirstPrediction(User $user): ?self
    {
        return self::createForUser($user->id, self::TYPE_FIRST_PREDICTION);
    }

    /**
     * Award social butterfly achievement.
     */
    public static function awardSocialButterfly(User $user): ?self
    {
        return self::createForUser($user->id, self::TYPE_SOCIAL_BUTTERFLY);
    }

    /**
     * Get user's achievement stats.
     */
    public static function getUserStats(int $userId): array
    {
        $achievements = self::where('user_id', $userId)->get();

        return [
            'total_achievements' => $achievements->count(),
            'total_points' => $achievements->sum('points_value'),
            'shared_achievements' => $achievements->whereNotNull('shared_at')->count(),
            'recent_achievements' => $achievements->where('earned_at', '>=', now()->subDays(7))->count(),
            'achievement_types' => $achievements->groupBy('achievement_type')->map->count(),
            'latest_achievement' => $achievements->sortByDesc('earned_at')->first(),
        ];
    }

    /**
     * Get leaderboard of users by achievement points.
     */
    public static function getAchievementLeaderboard(int $limit = 50): array
    {
        $leaderboard = self::select('user_id')
            ->selectRaw('SUM(points_value) as total_points')
            ->selectRaw('COUNT(*) as total_achievements')
            ->with('user:id,first_name,username')
            ->groupBy('user_id')
            ->orderBy('total_points', 'desc')
            ->orderBy('total_achievements', 'desc')
            ->limit($limit)
            ->get();

        return $leaderboard->map(function ($entry, $index) {
            return [
                'rank' => $index + 1,
                'user' => $entry->user,
                'total_points' => $entry->total_points,
                'total_achievements' => $entry->total_achievements,
            ];
        })->toArray();
    }

    /**
     * Get available sharing platforms.
     */
    public static function getAvailablePlatforms(): array
    {
        return [
            self::PLATFORM_TELEGRAM => 'Telegram',
            self::PLATFORM_TWITTER => 'Twitter',
            self::PLATFORM_FACEBOOK => 'Facebook',
            self::PLATFORM_INSTAGRAM => 'Instagram',
        ];
    }

    /**
     * Get all achievement types with descriptions.
     */
    public static function getAllAchievementTypes(): array
    {
        return [
            self::TYPE_FIRST_PREDICTION => 'First prediction made',
            self::TYPE_PERFECT_DAY => '100% accuracy for a day',
            self::TYPE_STREAK_MILESTONE => 'Consecutive prediction streaks',
            self::TYPE_BIG_WINNER => 'High daily winnings',
            self::TYPE_CONSISTENCY => 'Daily participation streaks',
            self::TYPE_SOCIAL_BUTTERFLY => 'Social sharing activities',
            self::TYPE_RISK_TAKER => 'High-stakes betting patterns',
            self::TYPE_CONSERVATIVE => 'Consistent small betting',
        ];
    }
}