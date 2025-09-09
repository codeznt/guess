<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictionQuestion extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'title',
        'description',
        'option_a',
        'option_b',
        'resolution_time',
        'resolution_criteria',
        'correct_answer',
        'status',
        'external_reference',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'resolution_time' => 'datetime',
        'category_id' => 'integer',
    ];

    /**
     * The possible statuses for a prediction question.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_RESOLVED = 'resolved';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * The possible correct answers.
     */
    public const ANSWER_A = 'A';
    public const ANSWER_B = 'B';

    /**
     * Get the category that owns the prediction question.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the predictions for this question.
     */
    public function predictions(): HasMany
    {
        return $this->hasMany(Prediction::class, 'question_id');
    }

    /**
     * Get the correct predictions for this question.
     */
    public function correctPredictions(): HasMany
    {
        return $this->predictions()->where('is_correct', true);
    }

    /**
     * Get the incorrect predictions for this question.
     */
    public function incorrectPredictions(): HasMany
    {
        return $this->predictions()->where('is_correct', false);
    }

    /**
     * Scope to get questions by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get active questions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('resolution_time', '>', now());
    }

    /**
     * Scope to get resolved questions.
     */
    public function scopeResolved($query)
    {
        return $query->where('status', self::STATUS_RESOLVED);
    }

    /**
     * Scope to get questions for a specific category.
     */
    public function scopeInCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    /**
     * Scope to get questions resolving soon (within specified hours).
     */
    public function scopeResolvingSoon($query, int $hours = 24)
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->whereBetween('resolution_time', [now(), now()->addHours($hours)]);
    }

    /**
     * Scope to get daily questions (active questions for today).
     */
    public function scopeDaily($query)
    {
        return $query->active()
            ->whereHas('category', function ($categoryQuery) {
                $categoryQuery->where('is_active', true);
            })
            ->orderBy('resolution_time')
            ->orderBy('created_at');
    }

    /**
     * Scope to get questions with user predictions.
     */
    public function scopeWithUserPrediction($query, int $userId)
    {
        return $query->with(['predictions' => function ($predictionQuery) use ($userId) {
            $predictionQuery->where('user_id', $userId);
        }]);
    }

    /**
     * Check if the question is accepting predictions.
     */
    public function isAcceptingPredictions(): bool
    {
        return $this->status === self::STATUS_ACTIVE && 
               $this->resolution_time > now();
    }

    /**
     * Check if the question is resolved.
     */
    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Check if the question is cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get the total number of predictions for this question.
     */
    public function getTotalPredictionsAttribute(): int
    {
        return $this->predictions()->count();
    }

    /**
     * Get the percentage of predictions for option A.
     */
    public function getOptionAPercentageAttribute(): float
    {
        $total = $this->total_predictions;
        if ($total === 0) {
            return 0.0;
        }

        $optionACount = $this->predictions()->where('choice', self::ANSWER_A)->count();
        return round(($optionACount / $total) * 100, 1);
    }

    /**
     * Get the percentage of predictions for option B.
     */
    public function getOptionBPercentageAttribute(): float
    {
        return round(100 - $this->option_a_percentage, 1);
    }

    /**
     * Get the time remaining until resolution.
     */
    public function getTimeRemainingAttribute(): ?string
    {
        if ($this->resolution_time <= now()) {
            return null;
        }

        $diff = now()->diffInMinutes($this->resolution_time);
        
        if ($diff < 60) {
            return $diff . ' minutes';
        } elseif ($diff < 1440) { // Less than 24 hours
            return now()->diffInHours($this->resolution_time) . ' hours';
        } else {
            return now()->diffInDays($this->resolution_time) . ' days';
        }
    }

    /**
     * Resolve the question with the correct answer.
     */
    public function resolve(string $correctAnswer): bool
    {
        if (!in_array($correctAnswer, [self::ANSWER_A, self::ANSWER_B])) {
            return false;
        }

        if (!$this->isAcceptingPredictions() && $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        $this->update([
            'status' => self::STATUS_RESOLVED,
            'correct_answer' => $correctAnswer,
        ]);

        // Update predictions with correct/incorrect status
        $this->predictions()->update([
            'is_correct' => \DB::raw("CASE WHEN choice = '{$correctAnswer}' THEN 1 ELSE 0 END"),
            'actual_winnings' => \DB::raw("CASE WHEN choice = '{$correctAnswer}' THEN potential_winnings ELSE 0 END"),
        ]);

        return true;
    }

    /**
     * Cancel the question.
     */
    public function cancel(): bool
    {
        if ($this->isResolved()) {
            return false;
        }

        $this->update(['status' => self::STATUS_CANCELLED]);

        // Refund all bets
        $predictions = $this->predictions;
        foreach ($predictions as $prediction) {
            $prediction->user->addWinnings($prediction->bet_amount);
        }

        return true;
    }

    /**
     * Get user's prediction for this question.
     */
    public function getUserPrediction(int $userId): ?Prediction
    {
        return $this->predictions()->where('user_id', $userId)->first();
    }

    /**
     * Check if user has already made a prediction.
     */
    public function hasUserPrediction(int $userId): bool
    {
        return $this->predictions()->where('user_id', $userId)->exists();
    }

    /**
     * Get the question's difficulty rating based on prediction split.
     */
    public function getDifficultyRatingAttribute(): string
    {
        $optionAPercentage = $this->option_a_percentage;
        $split = abs($optionAPercentage - 50);

        if ($split >= 40) {
            return 'Easy'; // 90-10 split or more extreme
        } elseif ($split >= 20) {
            return 'Medium'; // 70-30 to 89-11 split
        } else {
            return 'Hard'; // Close to 50-50 split
        }
    }
}