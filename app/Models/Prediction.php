<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prediction extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'question_id',
        'choice',
        'bet_amount',
        'potential_winnings',
        'actual_winnings',
        'is_correct',
        'multiplier_applied',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'question_id' => 'integer',
        'bet_amount' => 'integer',
        'potential_winnings' => 'integer',
        'actual_winnings' => 'integer',
        'is_correct' => 'boolean',
        'multiplier_applied' => 'decimal:2',
    ];

    /**
     * The possible choices for a prediction.
     */
    public const CHOICE_A = 'A';
    public const CHOICE_B = 'B';

    /**
     * Betting constraints.
     */
    public const MIN_BET = 10;
    public const MAX_BET = 1000;
    public const BASE_MULTIPLIER = 1.5;

    /**
     * Get the user that owns the prediction.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the question that this prediction answers.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(PredictionQuestion::class, 'question_id');
    }

    /**
     * Scope to get predictions by user.
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get predictions by question.
     */
    public function scopeByQuestion($query, int $questionId)
    {
        return $query->where('question_id', $questionId);
    }

    /**
     * Scope to get correct predictions.
     */
    public function scopeCorrect($query)
    {
        return $query->where('is_correct', true);
    }

    /**
     * Scope to get incorrect predictions.
     */
    public function scopeIncorrect($query)
    {
        return $query->where('is_correct', false);
    }

    /**
     * Scope to get resolved predictions (where is_correct is not null).
     */
    public function scopeResolved($query)
    {
        return $query->whereNotNull('is_correct');
    }

    /**
     * Scope to get unresolved predictions.
     */
    public function scopeUnresolved($query)
    {
        return $query->whereNull('is_correct');
    }

    /**
     * Scope to get predictions for today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', today());
    }

    /**
     * Scope to get predictions with winnings.
     */
    public function scopeWithWinnings($query)
    {
        return $query->where('actual_winnings', '>', 0);
    }

    /**
     * Calculate potential winnings based on bet amount and user's streak multiplier.
     */
    public static function calculatePotentialWinnings(int $betAmount, float $streakMultiplier = 1.0): int
    {
        $baseWinnings = $betAmount * self::BASE_MULTIPLIER;
        $finalWinnings = $baseWinnings * $streakMultiplier;
        
        return (int) round($finalWinnings);
    }

    /**
     * Create a new prediction with proper validation and calculations.
     */
    public static function createPrediction(User $user, PredictionQuestion $question, string $choice, int $betAmount): ?self
    {
        // Validate bet amount
        if ($betAmount < self::MIN_BET || $betAmount > self::MAX_BET) {
            throw new \InvalidArgumentException('Bet amount must be between ' . self::MIN_BET . ' and ' . self::MAX_BET . ' coins.');
        }

        // Check if user has sufficient coins
        if ($user->daily_coins < $betAmount) {
            throw new \InvalidArgumentException('Insufficient coins for bet.');
        }

        // Check if question is accepting predictions
        if (!$question->isAcceptingPredictions()) {
            throw new \InvalidArgumentException('Question is no longer accepting predictions.');
        }

        // Check if user already has a prediction for this question
        if ($question->hasUserPrediction($user->id)) {
            throw new \InvalidArgumentException('You have already made a prediction for this question.');
        }

        // Validate choice
        if (!in_array($choice, [self::CHOICE_A, self::CHOICE_B])) {
            throw new \InvalidArgumentException('Choice must be A or B.');
        }

        // Calculate potential winnings with streak multiplier
        $streakMultiplier = $user->streak_multiplier;
        $potentialWinnings = self::calculatePotentialWinnings($betAmount, $streakMultiplier);

        // Deduct coins from user
        if (!$user->deductCoins($betAmount)) {
            throw new \RuntimeException('Failed to deduct coins from user.');
        }

        // Create prediction
        $prediction = self::create([
            'user_id' => $user->id,
            'question_id' => $question->id,
            'choice' => $choice,
            'bet_amount' => $betAmount,
            'potential_winnings' => $potentialWinnings,
            'multiplier_applied' => $streakMultiplier,
        ]);

        return $prediction;
    }

    /**
     * Process the prediction result when question is resolved.
     */
    public function processResult(string $correctAnswer): void
    {
        $isCorrect = $this->choice === $correctAnswer;
        
        $this->update([
            'is_correct' => $isCorrect,
            'actual_winnings' => $isCorrect ? $this->potential_winnings : 0,
        ]);

        // Add winnings to user's daily coins if correct
        if ($isCorrect) {
            $this->user->addWinnings($this->actual_winnings);
        }

        // Update user's streak
        $this->user->updateStreak($isCorrect);
    }

    /**
     * Get the prediction's profit/loss amount.
     */
    public function getProfitLossAttribute(): int
    {
        if (is_null($this->is_correct)) {
            return 0; // Unresolved
        }

        return $this->actual_winnings - $this->bet_amount;
    }

    /**
     * Get the prediction's return on investment percentage.
     */
    public function getRoiPercentageAttribute(): float
    {
        if (is_null($this->is_correct) || $this->bet_amount === 0) {
            return 0.0;
        }

        return round((($this->actual_winnings - $this->bet_amount) / $this->bet_amount) * 100, 2);
    }

    /**
     * Check if the prediction is resolved.
     */
    public function isResolved(): bool
    {
        return !is_null($this->is_correct);
    }

    /**
     * Check if the prediction was correct.
     */
    public function isCorrect(): bool
    {
        return $this->is_correct === true;
    }

    /**
     * Check if the prediction was a winner (correct and has winnings).
     */
    public function isWinner(): bool
    {
        return $this->isCorrect() && $this->actual_winnings > 0;
    }

    /**
     * Get the choice label for display.
     */
    public function getChoiceLabelAttribute(): string
    {
        if (!$this->question) {
            return $this->choice;
        }

        return $this->choice === self::CHOICE_A 
            ? $this->question->option_a 
            : $this->question->option_b;
    }

    /**
     * Get the status label for display.
     */
    public function getStatusLabelAttribute(): string
    {
        if (is_null($this->is_correct)) {
            return 'Pending';
        }

        return $this->is_correct ? 'Won' : 'Lost';
    }

    /**
     * Get predictions with their question and category data.
     */
    public function scopeWithQuestionAndCategory($query)
    {
        return $query->with(['question.category']);
    }

    /**
     * Get user's daily prediction stats.
     */
    public static function getDailyStats(int $userId, string $date = null): array
    {
        $date = $date ?? today()->toDateString();
        
        $predictions = self::where('user_id', $userId)
            ->whereDate('created_at', $date)
            ->get();

        $totalPredictions = $predictions->count();
        $resolvedPredictions = $predictions->where('is_correct', '!==', null);
        $correctPredictions = $predictions->where('is_correct', true);
        $totalSpent = $predictions->sum('bet_amount');
        $totalWon = $predictions->sum('actual_winnings');

        return [
            'total_predictions' => $totalPredictions,
            'resolved_predictions' => $resolvedPredictions->count(),
            'correct_predictions' => $correctPredictions->count(),
            'accuracy_percentage' => $resolvedPredictions->count() > 0 
                ? round(($correctPredictions->count() / $resolvedPredictions->count()) * 100, 2)
                : 0.0,
            'total_spent' => $totalSpent,
            'total_won' => $totalWon,
            'net_profit' => $totalWon - $totalSpent,
        ];
    }

    /**
     * Validate prediction data before creation.
     */
    public static function validatePredictionData(array $data): array
    {
        $errors = [];

        if (!isset($data['question_id']) || !is_numeric($data['question_id'])) {
            $errors['question_id'] = 'Question ID is required and must be numeric.';
        }

        if (!isset($data['choice']) || !in_array($data['choice'], [self::CHOICE_A, self::CHOICE_B])) {
            $errors['choice'] = 'Choice must be A or B.';
        }

        if (!isset($data['bet_amount']) || !is_numeric($data['bet_amount'])) {
            $errors['bet_amount'] = 'Bet amount is required and must be numeric.';
        } else {
            $betAmount = (int) $data['bet_amount'];
            if ($betAmount < self::MIN_BET) {
                $errors['bet_amount'] = 'Minimum bet amount is ' . self::MIN_BET . ' coins.';
            } elseif ($betAmount > self::MAX_BET) {
                $errors['bet_amount'] = 'Maximum bet amount is ' . self::MAX_BET . ' coins.';
            }
        }

        return $errors;
    }
}