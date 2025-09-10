<?php

namespace App\Http\Controllers;

use App\Models\PredictionQuestion;
use App\Services\PredictionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuestionsController extends Controller
{
    public function __construct(
        protected PredictionService $predictionService
    ) {}

    /**
     * Display daily prediction questions for authenticated user.
     */
    public function daily(Request $request): Response
    {
        $user = $request->user();
        
        // Get active questions for today with their categories and user predictions
        $questions = PredictionQuestion::with(['category', 'predictions' => function ($query) use ($user) {
                $query->where('user_id', $user->id);
            }])
            ->where('status', 'active')
            ->where('resolution_time', '>', now())
            ->orderBy('resolution_time')
            ->orderBy('created_at')
            ->get()
            ->map(function ($question) use ($user) {
                $userPrediction = $question->predictions->first();
                
                return [
                    'id' => $question->id,
                    'title' => $question->title,
                    'option_a' => $question->option_a,
                    'option_b' => $question->option_b,
                    'resolution_time' => $question->resolution_time->toISOString(),
                    'is_resolved' => $question->status === 'resolved',
                    'correct_answer' => $question->correct_answer,
                    'category' => [
                        'id' => $question->category->id,
                        'name' => $question->category->name,
                        'icon' => $question->category->icon,
                        'color' => $question->category->color,
                    ],
                    'user_prediction' => $userPrediction ? [
                        'id' => $userPrediction->id,
                        'choice' => $userPrediction->choice,
                        'bet_amount' => $userPrediction->bet_amount,
                        'potential_winnings' => $userPrediction->potential_winnings,
                        'actual_winnings' => $userPrediction->actual_winnings,
                        'is_correct' => $userPrediction->is_correct,
                    ] : null,
                ];
            });

        // Get user's current stats
        $userStats = [
            'daily_coins' => $user->daily_coins,
            'current_streak' => $user->current_streak,
            'best_streak' => $user->best_streak,
            'predictions_made_today' => $user->predictions()
                ->whereDate('created_at', today())
                ->count(),
        ];

        // Meta information about the daily questions
        $meta = [
            'date' => now()->format('Y-m-d'),
            'total_questions' => $questions->count(),
            'predictions_made' => $questions->filter(fn($q) => $q['user_prediction'])->count(),
            'remaining_questions' => $questions->filter(fn($q) => !$q['user_prediction'])->count(),
        ];

        return Inertia::render('Questions/Daily', [
            'questions' => $questions->values(),
            'userCoins' => $user->daily_coins,
            'user' => array_merge([
                'id' => $user->id,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'telegram_id' => $user->telegram_id,
            ], $userStats),
            'meta' => $meta,
        ]);
    }

    /**
     * Get question statistics for display purposes.
     */
    public function stats(Request $request, PredictionQuestion $question): array
    {
        $totalPredictions = $question->predictions()->count();
        
        if ($totalPredictions === 0) {
            return [
                'total_predictions' => 0,
                'option_a_percentage' => 50,
                'option_b_percentage' => 50,
                'total_wagered' => 0,
            ];
        }

        $optionACount = $question->predictions()->where('choice', 'A')->count();
        $optionBCount = $question->predictions()->where('choice', 'B')->count();
        $totalWagered = $question->predictions()->sum('bet_amount');

        return [
            'total_predictions' => $totalPredictions,
            'option_a_percentage' => round(($optionACount / $totalPredictions) * 100, 1),
            'option_b_percentage' => round(($optionBCount / $totalPredictions) * 100, 1),
            'total_wagered' => $totalWagered,
        ];
    }
}