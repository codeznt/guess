<?php

namespace App\Services;

use App\Models\User;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BettingService
{
    /**
     * The base multiplier for winnings calculation.
     */
    public const BASE_MULTIPLIER = 1.5;

    /**
     * Maximum daily coins a user can have.
     */
    public const MAX_DAILY_COINS = 1000;

    /**
     * Submit a single prediction with bet.
     */
    public function submitPrediction(User $user, PredictionQuestion $question, string $choice, int $betAmount): Prediction
    {
        return DB::transaction(function () use ($user, $question, $choice, $betAmount) {
            // Use the Prediction model's validation and creation method
            $prediction = Prediction::createPrediction($user, $question, $choice, $betAmount);

            Log::info('Prediction submitted', [
                'user_id' => $user->id,
                'question_id' => $question->id,
                'choice' => $choice,
                'bet_amount' => $betAmount,
                'potential_winnings' => $prediction->potential_winnings,
                'streak_multiplier' => $prediction->multiplier_applied,
            ]);

            return $prediction;
        });
    }

    /**
     * Submit multiple predictions atomically.
     */
    public function submitMultiplePredictions(User $user, array $predictions): array
    {
        // Validate all predictions first
        $this->validateMultiplePredictions($user, $predictions);

        return DB::transaction(function () use ($user, $predictions) {
            $createdPredictions = [];
            $totalBetAmount = 0;

            foreach ($predictions as $predictionData) {
                $question = PredictionQuestion::findOrFail($predictionData['question_id']);
                
                $prediction = $this->submitPrediction(
                    $user,
                    $question,
                    $predictionData['choice'],
                    $predictionData['bet_amount']
                );
                
                $createdPredictions[] = $prediction;
                $totalBetAmount += $predictionData['bet_amount'];
            }

            Log::info('Multiple predictions submitted', [
                'user_id' => $user->id,
                'predictions_count' => count($createdPredictions),
                'total_bet_amount' => $totalBetAmount,
                'remaining_coins' => $user->fresh()->daily_coins,
            ]);

            return [
                'predictions' => $createdPredictions,
                'total_bet' => $totalBetAmount,
                'remaining_coins' => $user->fresh()->daily_coins,
            ];
        });
    }

    /**
     * Calculate potential winnings for a bet.
     */
    public function calculatePotentialWinnings(int $betAmount, User $user): array
    {
        $baseWinnings = $betAmount * self::BASE_MULTIPLIER;
        $streakMultiplier = $user->streak_multiplier;
        $finalWinnings = (int) round($baseWinnings * $streakMultiplier);

        return [
            'bet_amount' => $betAmount,
            'base_winnings' => (int) round($baseWinnings),
            'streak_multiplier' => $streakMultiplier,
            'potential_winnings' => $finalWinnings,
            'profit' => $finalWinnings - $betAmount,
            'roi_percentage' => round((($finalWinnings - $betAmount) / $betAmount) * 100, 2),
        ];
    }

    /**
     * Process winnings after question resolution.
     */
    public function processWinnings(Prediction $prediction, string $correctAnswer): array
    {
        return DB::transaction(function () use ($prediction, $correctAnswer) {
            $isCorrect = $prediction->choice === $correctAnswer;
            $actualWinnings = $isCorrect ? $prediction->potential_winnings : 0;

            $prediction->update([
                'is_correct' => $isCorrect,
                'actual_winnings' => $actualWinnings,
            ]);

            if ($isCorrect) {
                $prediction->user->addWinnings($actualWinnings);
            }

            Log::info('Winnings processed', [
                'prediction_id' => $prediction->id,
                'user_id' => $prediction->user_id,
                'is_correct' => $isCorrect,
                'actual_winnings' => $actualWinnings,
                'profit_loss' => $actualWinnings - $prediction->bet_amount,
            ]);

            return [
                'is_correct' => $isCorrect,
                'actual_winnings' => $actualWinnings,
                'profit_loss' => $actualWinnings - $prediction->bet_amount,
                'roi_percentage' => $prediction->roi_percentage,
            ];
        });
    }

    /**
     * Reset daily coins for all users.
     */
    public function resetDailyCoins(): int
    {
        $usersReset = User::whereColumn('daily_coins', '<', DB::raw(self::MAX_DAILY_COINS))
            ->update(['daily_coins' => self::MAX_DAILY_COINS]);

        Log::info('Daily coins reset completed', [
            'users_reset' => $usersReset,
            'reset_amount' => self::MAX_DAILY_COINS,
        ]);

        return $usersReset;
    }

    /**
     * Get user's betting statistics.
     */
    public function getUserBettingStats(User $user, int $days = 30): array
    {
        $predictions = Prediction::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays($days))
            ->get();

        $totalBets = $predictions->count();
        $totalWagered = $predictions->sum('bet_amount');
        $totalWinnings = $predictions->sum('actual_winnings');
        $resolvedPredictions = $predictions->whereNotNull('is_correct');
        $correctPredictions = $predictions->where('is_correct', true);

        return [
            'period_days' => $days,
            'total_bets' => $totalBets,
            'total_wagered' => $totalWagered,
            'total_winnings' => $totalWinnings,
            'net_profit' => $totalWinnings - $totalWagered,
            'roi_percentage' => $totalWagered > 0 ? round((($totalWinnings - $totalWagered) / $totalWagered) * 100, 2) : 0,
            'win_rate' => $resolvedPredictions->count() > 0 ? round(($correctPredictions->count() / $resolvedPredictions->count()) * 100, 2) : 0,
            'average_bet' => $totalBets > 0 ? round($totalWagered / $totalBets, 2) : 0,
            'average_winnings' => $correctPredictions->count() > 0 ? round($correctPredictions->sum('actual_winnings') / $correctPredictions->count(), 2) : 0,
            'biggest_win' => $predictions->max('actual_winnings') ?? 0,
            'biggest_loss' => $predictions->max('bet_amount') ?? 0,
            'current_streak' => $user->current_streak,
            'streak_multiplier' => $user->streak_multiplier,
        ];
    }

    /**
     * Get betting patterns analysis.
     */
    public function getUserBettingPatterns(User $user): array
    {
        $predictions = Prediction::where('user_id', $user->id)->get();
        
        if ($predictions->isEmpty()) {
            return [
                'betting_style' => 'No data',
                'risk_profile' => 'Unknown',
                'patterns' => [],
            ];
        }

        $totalWagered = $predictions->sum('bet_amount');
        $averageBet = $totalWagered / $predictions->count();
        $betAmounts = $predictions->pluck('bet_amount');
        
        $lowBets = $betAmounts->filter(fn($bet) => $bet <= 50)->count();
        $mediumBets = $betAmounts->filter(fn($bet) => $bet > 50 && $bet <= 200)->count();
        $highBets = $betAmounts->filter(fn($bet) => $bet > 200)->count();

        // Determine betting style
        $bettingStyle = 'Balanced';
        if ($lowBets > $predictions->count() * 0.7) {
            $bettingStyle = 'Conservative';
        } elseif ($highBets > $predictions->count() * 0.3) {
            $bettingStyle = 'Aggressive';
        }

        // Determine risk profile
        $riskProfile = 'Medium';
        if ($averageBet <= 75) {
            $riskProfile = 'Low';
        } elseif ($averageBet >= 250) {
            $riskProfile = 'High';
        }

        return [
            'betting_style' => $bettingStyle,
            'risk_profile' => $riskProfile,
            'average_bet' => round($averageBet, 2),
            'patterns' => [
                'low_bets_percentage' => round(($lowBets / $predictions->count()) * 100, 1),
                'medium_bets_percentage' => round(($mediumBets / $predictions->count()) * 100, 1),
                'high_bets_percentage' => round(($highBets / $predictions->count()) * 100, 1),
                'most_common_bet' => $betAmounts->mode()->first() ?? 0,
                'bet_variance' => $this->calculateVariance($betAmounts),
            ],
        ];
    }

    /**
     * Get coin flow analysis for a user.
     */
    public function getUserCoinFlow(User $user, string $startDate = null, string $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7)->toDateString();
        $endDate = $endDate ?? today()->toDateString();

        $predictions = Prediction::where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at')
            ->get();

        $dailyFlow = [];
        $runningBalance = $user->daily_coins;

        // Group by date
        $predictionsByDate = $predictions->groupBy(function ($prediction) {
            return $prediction->created_at->toDateString();
        });

        foreach ($predictionsByDate as $date => $dayPredictions) {
            $dailySpent = $dayPredictions->sum('bet_amount');
            $dailyWinnings = $dayPredictions->sum('actual_winnings');
            $dailyProfit = $dailyWinnings - $dailySpent;

            $dailyFlow[] = [
                'date' => $date,
                'spent' => $dailySpent,
                'winnings' => $dailyWinnings,
                'profit' => $dailyProfit,
                'predictions_count' => $dayPredictions->count(),
                'correct_predictions' => $dayPredictions->where('is_correct', true)->count(),
            ];
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_spent' => $predictions->sum('bet_amount'),
            'total_winnings' => $predictions->sum('actual_winnings'),
            'net_profit' => $predictions->sum('actual_winnings') - $predictions->sum('bet_amount'),
            'daily_flow' => $dailyFlow,
            'current_balance' => $user->daily_coins,
        ];
    }

    /**
     * Get system-wide betting statistics.
     */
    public function getSystemBettingStats(string $date = null): array
    {
        $date = $date ?? today()->toDateString();

        $dailyPredictions = Prediction::whereDate('created_at', $date);
        $totalWagered = $dailyPredictions->sum('bet_amount');
        $totalWinnings = $dailyPredictions->sum('actual_winnings');
        $totalPredictions = $dailyPredictions->count();
        $uniqueBettors = $dailyPredictions->distinct('user_id')->count('user_id');

        return [
            'date' => $date,
            'total_predictions' => $totalPredictions,
            'unique_bettors' => $uniqueBettors,
            'total_wagered' => $totalWagered,
            'total_winnings' => $totalWinnings,
            'house_edge' => $totalWagered > 0 ? round((($totalWagered - $totalWinnings) / $totalWagered) * 100, 2) : 0,
            'average_bet' => $totalPredictions > 0 ? round($totalWagered / $totalPredictions, 2) : 0,
            'payout_ratio' => $totalWagered > 0 ? round($totalWinnings / $totalWagered, 3) : 0,
        ];
    }

    /**
     * Validate multiple predictions before submission.
     */
    private function validateMultiplePredictions(User $user, array $predictions): void
    {
        if (empty($predictions)) {
            throw new \InvalidArgumentException('No predictions provided');
        }

        if (count($predictions) > 12) {
            throw new \InvalidArgumentException('Maximum 12 predictions allowed per submission');
        }

        $totalBetAmount = 0;
        $questionIds = [];

        foreach ($predictions as $predictionData) {
            // Validate prediction data structure
            $errors = Prediction::validatePredictionData($predictionData);
            if (!empty($errors)) {
                throw new \InvalidArgumentException('Invalid prediction data: ' . implode(', ', $errors));
            }

            $totalBetAmount += $predictionData['bet_amount'];
            
            // Check for duplicate questions
            if (in_array($predictionData['question_id'], $questionIds)) {
                throw new \InvalidArgumentException('Duplicate question in predictions');
            }
            $questionIds[] = $predictionData['question_id'];

            // Validate question exists and is active
            $question = PredictionQuestion::find($predictionData['question_id']);
            if (!$question || !$question->isAcceptingPredictions()) {
                throw new \InvalidArgumentException("Question {$predictionData['question_id']} is not accepting predictions");
            }

            // Check if user already has prediction for this question
            if ($question->hasUserPrediction($user->id)) {
                throw new \InvalidArgumentException("Already have prediction for question {$predictionData['question_id']}");
            }
        }

        // Check if user has sufficient coins for all bets
        if ($user->daily_coins < $totalBetAmount) {
            throw new \InvalidArgumentException("Insufficient coins. Need {$totalBetAmount}, have {$user->daily_coins}");
        }
    }

    /**
     * Calculate variance for bet amounts.
     */
    private function calculateVariance($values): float
    {
        if ($values->count() <= 1) {
            return 0.0;
        }

        $mean = $values->avg();
        $sumOfSquares = $values->sum(function ($value) use ($mean) {
            return pow($value - $mean, 2);
        });

        return round($sumOfSquares / $values->count(), 2);
    }

    /**
     * Get recommended bet amount based on user's pattern.
     */
    public function getRecommendedBetAmount(User $user, PredictionQuestion $question): array
    {
        $userStats = $this->getUserBettingStats($user, 14); // Last 2 weeks
        $patterns = $this->getUserBettingPatterns($user);

        // Base recommendation on user's average bet
        $averageBet = $userStats['average_bet'] ?: 100;
        
        // Adjust based on current streak
        $streakAdjustment = 1.0;
        if ($user->current_streak >= 5) {
            $streakAdjustment = 1.2; // Increase bet on hot streak
        } elseif ($user->current_streak === 0 && $userStats['net_profit'] < 0) {
            $streakAdjustment = 0.8; // Decrease bet after losses
        }

        $recommendedAmount = (int) round($averageBet * $streakAdjustment);
        
        // Ensure within limits and user's available coins
        $recommendedAmount = max(Prediction::MIN_BET, min(
            $recommendedAmount,
            Prediction::MAX_BET,
            (int) ($user->daily_coins * 0.3) // Max 30% of available coins
        ));

        return [
            'recommended_amount' => $recommendedAmount,
            'reasoning' => $this->getBetRecommendationReasoning($user, $streakAdjustment, $patterns),
            'confidence_level' => $this->calculateRecommendationConfidence($userStats),
            'potential_winnings' => $this->calculatePotentialWinnings($recommendedAmount, $user),
        ];
    }

    /**
     * Get reasoning for bet recommendation.
     */
    private function getBetRecommendationReasoning(User $user, float $adjustment, array $patterns): string
    {
        $reasons = [];

        if ($user->current_streak >= 5) {
            $reasons[] = "increased due to {$user->current_streak}-day hot streak";
        } elseif ($user->current_streak === 0) {
            $reasons[] = "reduced to rebuild momentum";
        }

        if ($patterns['risk_profile'] === 'High') {
            $reasons[] = "adjusted for aggressive betting style";
        } elseif ($patterns['risk_profile'] === 'Low') {
            $reasons[] = "aligned with conservative approach";
        }

        return empty($reasons) 
            ? "Based on your average betting pattern" 
            : "Recommended amount " . implode(', ', $reasons);
    }

    /**
     * Calculate confidence level for recommendation.
     */
    private function calculateRecommendationConfidence(array $stats): string
    {
        if ($stats['total_bets'] >= 20 && $stats['roi_percentage'] >= 0) {
            return 'High';
        } elseif ($stats['total_bets'] >= 10) {
            return 'Medium';
        } else {
            return 'Low';
        }
    }
}