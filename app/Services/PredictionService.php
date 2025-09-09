<?php

namespace App\Services;

use App\Models\Category;
use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class PredictionService
{
    /**
     * Get daily prediction questions for users.
     */
    public function getDailyQuestions(User $user = null): array
    {
        $questions = PredictionQuestion::daily()
            ->with(['category'])
            ->limit(12)
            ->get();

        // Add user's existing predictions if user provided
        if ($user) {
            $questions = $questions->map(function ($question) use ($user) {
                $userPrediction = $question->getUserPrediction($user->id);
                $question->user_prediction = $userPrediction ? [
                    'choice' => $userPrediction->choice,
                    'bet_amount' => $userPrediction->bet_amount,
                    'potential_winnings' => $userPrediction->potential_winnings,
                    'multiplier_applied' => $userPrediction->multiplier_applied,
                ] : null;
                
                return $question;
            });
        }

        return [
            'questions' => $questions,
            'meta' => [
                'date' => today()->toDateString(),
                'total_questions' => $questions->count(),
                'categories_count' => $questions->pluck('category_id')->unique()->count(),
            ],
        ];
    }

    /**
     * Create a new prediction question.
     */
    public function createQuestion(array $data): PredictionQuestion
    {
        $this->validateQuestionData($data);

        return DB::transaction(function () use ($data) {
            $question = PredictionQuestion::create([
                'category_id' => $data['category_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'option_a' => $data['option_a'],
                'option_b' => $data['option_b'],
                'resolution_time' => Carbon::parse($data['resolution_time']),
                'resolution_criteria' => $data['resolution_criteria'],
                'status' => $data['status'] ?? PredictionQuestion::STATUS_PENDING,
                'external_reference' => $data['external_reference'] ?? null,
            ]);

            Log::info('Prediction question created', [
                'question_id' => $question->id,
                'title' => $question->title,
                'category' => $question->category->name,
                'resolution_time' => $question->resolution_time,
            ]);

            return $question;
        });
    }

    /**
     * Update an existing prediction question.
     */
    public function updateQuestion(PredictionQuestion $question, array $data): PredictionQuestion
    {
        if ($question->isResolved()) {
            throw new \InvalidArgumentException('Cannot update resolved question');
        }

        return DB::transaction(function () use ($question, $data) {
            $question->update(array_filter([
                'title' => $data['title'] ?? null,
                'description' => $data['description'] ?? null,
                'option_a' => $data['option_a'] ?? null,
                'option_b' => $data['option_b'] ?? null,
                'resolution_time' => isset($data['resolution_time']) ? Carbon::parse($data['resolution_time']) : null,
                'resolution_criteria' => $data['resolution_criteria'] ?? null,
                'status' => $data['status'] ?? null,
                'external_reference' => $data['external_reference'] ?? null,
            ]));

            Log::info('Prediction question updated', [
                'question_id' => $question->id,
                'changes' => $data,
            ]);

            return $question->fresh();
        });
    }

    /**
     * Resolve a prediction question with the correct answer.
     */
    public function resolveQuestion(PredictionQuestion $question, string $correctAnswer): bool
    {
        if ($question->isResolved()) {
            throw new \InvalidArgumentException('Question is already resolved');
        }

        if (!in_array($correctAnswer, [PredictionQuestion::ANSWER_A, PredictionQuestion::ANSWER_B])) {
            throw new \InvalidArgumentException('Correct answer must be A or B');
        }

        return DB::transaction(function () use ($question, $correctAnswer) {
            $success = $question->resolve($correctAnswer);
            
            if ($success) {
                // Process all predictions for this question
                $predictions = $question->predictions;
                $correctPredictions = 0;
                $totalWinnings = 0;

                foreach ($predictions as $prediction) {
                    $prediction->processResult($correctAnswer);
                    
                    if ($prediction->is_correct) {
                        $correctPredictions++;
                        $totalWinnings += $prediction->actual_winnings;
                    }
                }

                Log::info('Prediction question resolved', [
                    'question_id' => $question->id,
                    'correct_answer' => $correctAnswer,
                    'total_predictions' => $predictions->count(),
                    'correct_predictions' => $correctPredictions,
                    'total_winnings_paid' => $totalWinnings,
                ]);
            }

            return $success;
        });
    }

    /**
     * Cancel a prediction question and refund all bets.
     */
    public function cancelQuestion(PredictionQuestion $question, string $reason = null): bool
    {
        if ($question->isResolved()) {
            throw new \InvalidArgumentException('Cannot cancel resolved question');
        }

        return DB::transaction(function () use ($question, $reason) {
            $predictions = $question->predictions;
            $totalRefunded = 0;

            foreach ($predictions as $prediction) {
                $prediction->user->addWinnings($prediction->bet_amount);
                $totalRefunded += $prediction->bet_amount;
            }

            $success = $question->cancel();

            if ($success) {
                Log::info('Prediction question cancelled', [
                    'question_id' => $question->id,
                    'reason' => $reason,
                    'predictions_refunded' => $predictions->count(),
                    'total_refunded' => $totalRefunded,
                ]);
            }

            return $success;
        });
    }

    /**
     * Get questions that need resolution (past resolution time).
     */
    public function getQuestionsNeedingResolution(): Collection
    {
        return PredictionQuestion::where('status', PredictionQuestion::STATUS_ACTIVE)
            ->where('resolution_time', '<=', now())
            ->get();
    }

    /**
     * Activate pending questions that should become active.
     */
    public function activatePendingQuestions(): int
    {
        $activated = PredictionQuestion::where('status', PredictionQuestion::STATUS_PENDING)
            ->where('resolution_time', '>', now()->addMinutes(30)) // Give at least 30min for predictions
            ->update(['status' => PredictionQuestion::STATUS_ACTIVE]);

        if ($activated > 0) {
            Log::info("Activated {$activated} pending questions");
        }

        return $activated;
    }

    /**
     * Get question statistics.
     */
    public function getQuestionStats(PredictionQuestion $question): array
    {
        $predictions = $question->predictions;
        $totalPredictions = $predictions->count();

        if ($totalPredictions === 0) {
            return [
                'total_predictions' => 0,
                'option_a_count' => 0,
                'option_b_count' => 0,
                'option_a_percentage' => 0,
                'option_b_percentage' => 0,
                'total_coins_wagered' => 0,
                'average_bet' => 0,
                'difficulty_rating' => 'Unknown',
            ];
        }

        $optionACount = $predictions->where('choice', PredictionQuestion::ANSWER_A)->count();
        $optionBCount = $totalPredictions - $optionACount;
        $totalWagered = $predictions->sum('bet_amount');

        return [
            'total_predictions' => $totalPredictions,
            'option_a_count' => $optionACount,
            'option_b_count' => $optionBCount,
            'option_a_percentage' => round(($optionACount / $totalPredictions) * 100, 1),
            'option_b_percentage' => round(($optionBCount / $totalPredictions) * 100, 1),
            'total_coins_wagered' => $totalWagered,
            'average_bet' => round($totalWagered / $totalPredictions, 2),
            'difficulty_rating' => $this->calculateDifficulty($optionACount, $totalPredictions),
        ];
    }

    /**
     * Get predictions by category for analytics.
     */
    public function getPredictionsByCategory(string $startDate = null, string $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(7)->toDateString();
        $endDate = $endDate ?? today()->toDateString();

        $categoryStats = DB::table('prediction_questions')
            ->join('categories', 'prediction_questions.category_id', '=', 'categories.id')
            ->join('predictions', 'prediction_questions.id', '=', 'predictions.question_id')
            ->whereBetween('predictions.created_at', [$startDate, $endDate])
            ->groupBy('categories.id', 'categories.name')
            ->select([
                'categories.id',
                'categories.name',
                DB::raw('COUNT(predictions.id) as total_predictions'),
                DB::raw('SUM(CASE WHEN predictions.is_correct = 1 THEN 1 ELSE 0 END) as correct_predictions'),
                DB::raw('AVG(CASE WHEN predictions.is_correct = 1 THEN predictions.actual_winnings ELSE 0 END) as avg_winnings'),
                DB::raw('COUNT(DISTINCT prediction_questions.id) as total_questions'),
            ])
            ->get()
            ->map(function ($stat) {
                $stat->accuracy_percentage = $stat->total_predictions > 0 
                    ? round(($stat->correct_predictions / $stat->total_predictions) * 100, 2) 
                    : 0;
                return $stat;
            });

        return $categoryStats->toArray();
    }

    /**
     * Bulk create questions from external data source.
     */
    public function bulkCreateQuestions(array $questionsData): array
    {
        $created = [];
        $failed = [];

        foreach ($questionsData as $questionData) {
            try {
                $question = $this->createQuestion($questionData);
                $created[] = $question->id;
            } catch (\Exception $e) {
                $failed[] = [
                    'data' => $questionData,
                    'error' => $e->getMessage(),
                ];
            }
        }

        Log::info('Bulk question creation completed', [
            'created_count' => count($created),
            'failed_count' => count($failed),
            'created_ids' => $created,
        ]);

        return [
            'created' => $created,
            'failed' => $failed,
        ];
    }

    /**
     * Get user's prediction history with questions.
     */
    public function getUserPredictionHistory(User $user, int $limit = 50): Collection
    {
        return Prediction::where('user_id', $user->id)
            ->with(['question.category'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get trending questions (most predicted).
     */
    public function getTrendingQuestions(int $limit = 10): Collection
    {
        return PredictionQuestion::withCount('predictions')
            ->where('status', PredictionQuestion::STATUS_ACTIVE)
            ->orderBy('predictions_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate question data.
     */
    private function validateQuestionData(array $data): void
    {
        $required = ['category_id', 'title', 'option_a', 'option_b', 'resolution_time', 'resolution_criteria'];
        
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException("Field {$field} is required");
            }
        }

        // Validate category exists and is active
        $category = Category::find($data['category_id']);
        if (!$category || !$category->is_active) {
            throw new \InvalidArgumentException('Invalid or inactive category');
        }

        // Validate resolution time is in the future
        $resolutionTime = Carbon::parse($data['resolution_time']);
        if ($resolutionTime <= now()) {
            throw new \InvalidArgumentException('Resolution time must be in the future');
        }

        // Validate title length
        if (strlen($data['title']) < 10 || strlen($data['title']) > 255) {
            throw new \InvalidArgumentException('Title must be between 10 and 255 characters');
        }

        // Validate options are different
        if ($data['option_a'] === $data['option_b']) {
            throw new \InvalidArgumentException('Options A and B must be different');
        }
    }

    /**
     * Calculate question difficulty based on prediction split.
     */
    private function calculateDifficulty(int $optionACount, int $totalPredictions): string
    {
        if ($totalPredictions === 0) {
            return 'Unknown';
        }

        $percentage = ($optionACount / $totalPredictions) * 100;
        $split = abs($percentage - 50);

        if ($split >= 40) {
            return 'Easy'; // 90-10 split or more extreme
        } elseif ($split >= 20) {
            return 'Medium'; // 70-30 to 89-11 split
        } else {
            return 'Hard'; // Close to 50-50 split
        }
    }

    /**
     * Get daily question statistics.
     */
    public function getDailyStats(string $date = null): array
    {
        $date = $date ?? today()->toDateString();

        $questions = PredictionQuestion::whereDate('created_at', $date)->get();
        $totalPredictions = Prediction::whereDate('created_at', $date)->count();
        $resolvedQuestions = $questions->where('status', PredictionQuestion::STATUS_RESOLVED)->count();
        $activeQuestions = $questions->where('status', PredictionQuestion::STATUS_ACTIVE)->count();

        return [
            'date' => $date,
            'total_questions' => $questions->count(),
            'active_questions' => $activeQuestions,
            'resolved_questions' => $resolvedQuestions,
            'total_predictions' => $totalPredictions,
            'average_predictions_per_question' => $questions->count() > 0 ? round($totalPredictions / $questions->count(), 2) : 0,
        ];
    }
}