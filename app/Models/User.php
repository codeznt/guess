<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'telegram_id',
        'username',
        'first_name',
        'last_name',
        'daily_coins',
        'total_predictions',
        'correct_predictions',
        'current_streak',
        'best_streak',
        'last_active_date',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'last_active_date' => 'date',
            'daily_coins' => 'integer',
            'total_predictions' => 'integer',
            'correct_predictions' => 'integer',
            'current_streak' => 'integer',
            'best_streak' => 'integer',
        ];
    }

    /**
     * Get the user's predictions.
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class);
    }

    /**
     * Get the user's achievements.
     */
    public function achievements(): HasMany
    {
        return $this->hasMany(Achievement::class);
    }

    /**
     * Get the user's daily leaderboard entries.
     */
    public function dailyLeaderboards(): HasMany
    {
        return $this->hasMany(DailyLeaderboard::class);
    }

    /**
     * Calculate the user's accuracy percentage.
     */
    public function getAccuracyPercentageAttribute(): float
    {
        if ($this->total_predictions === 0) {
            return 0.0;
        }

        return round(($this->correct_predictions / $this->total_predictions) * 100, 2);
    }

    /**
     * Get the streak multiplier based on current streak.
     */
    public function getStreakMultiplierAttribute(): float
    {
        // 1% bonus per streak day, capped at 100% (2.0x total)
        $bonus = min($this->current_streak * 0.01, 1.0);
        return round(1.0 + $bonus, 2);
    }

    /**
     * Check if this is a Telegram user.
     */
    public function isTelegramUser(): bool
    {
        return !is_null($this->telegram_id);
    }

    /**
     * Get user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->first_name && $this->last_name) {
            return $this->first_name . ' ' . $this->last_name;
        }

        return $this->first_name ?? $this->username ?? $this->name ?? 'Anonymous';
    }

    /**
     * Scope to find user by Telegram ID.
     */
    public function scopeByTelegramId($query, int $telegramId)
    {
        return $query->where('telegram_id', $telegramId);
    }

    /**
     * Scope to get active users (made predictions recently).
     */
    public function scopeActive($query, int $days = 7)
    {
        return $query->where('last_active_date', '>=', now()->subDays($days)->toDateString());
    }

    /**
     * Reset daily coins to 1000 (called by daily reset job).
     */
    public function resetDailyCoins(): void
    {
        $this->update(['daily_coins' => 1000]);
    }

    /**
     * Deduct coins for a bet.
     */
    public function deductCoins(int $amount): bool
    {
        if ($this->daily_coins < $amount) {
            return false;
        }

        $this->decrement('daily_coins', $amount);
        return true;
    }

    /**
     * Add winnings to daily coins.
     */
    public function addWinnings(int $amount): void
    {
        $this->increment('daily_coins', $amount);
    }

    /**
     * Update streak after prediction result.
     */
    public function updateStreak(bool $isCorrect): void
    {
        if ($isCorrect) {
            $newStreak = $this->current_streak + 1;
            $this->update([
                'current_streak' => $newStreak,
                'best_streak' => max($this->best_streak, $newStreak),
                'correct_predictions' => $this->correct_predictions + 1,
            ]);
        } else {
            $this->update(['current_streak' => 0]);
        }

        $this->increment('total_predictions');
        $this->update(['last_active_date' => now()->toDateString()]);
    }
}
