<?php

namespace App\Http\Controllers;

use App\Models\PredictionQuestion;
use App\Models\Prediction;
use App\Services\BettingService;
use App\Services\PredictionService;
use App\Services\StreakService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PredictionsController extends Controller
{
    public function __construct(
        protected BettingService $bettingService,
        protected PredictionService $predictionService,
        protected StreakService $streakService
    ) {}

    /**
     * Submit predictions for multiple questions.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        // Validate the request structure
        $validator = Validator::make($request->all(), [
            'predictions' => 'required|array|min:1',
            'predictions.*.question_id' => 'required|integer|exists:prediction_questions,id',
            'predictions.*.choice' => 'required|in:A,B',
            'predictions.*.bet_amount' => 'required|integer|min:10|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $predictions = $request->input('predictions');
        
        try {
            return DB::transaction(function () use ($predictions, $user) {
                $totalBetAmount = 0;
                $validatedPredictions = [];

                // First pass: validate all predictions and calculate total bet
                foreach ($predictions as $index => $predictionData) {
                    $question = PredictionQuestion::findOrFail($predictionData['question_id']);
                    $betAmount = $predictionData['bet_amount'];
                    $choice = $predictionData['choice'];

                    // Validate question is still accepting predictions
                    if ($question->status !== 'active' || $question->resolution_time <= now()) {
                        throw ValidationException::withMessages([
                            'question' => 'Question is no longer accepting predictions'
                        ]);
                    }

                    // Check if user already has a prediction for this question
                    if ($question->predictions()->where('user_id', $user->id)->exists()) {
                        throw ValidationException::withMessages([
                            'question' => 'You have already made a prediction for this question'
                        ]);
                    }

                    // Validate bet amount limits
                    if ($betAmount < 10) {
                        throw ValidationException::withMessages([
                            'bet_amount' => 'Minimum bet amount is 10 coins'
                        ]);
                    }

                    if ($betAmount > 1000) {
                        throw ValidationException::withMessages([
                            'bet_amount' => 'Maximum bet amount is 1000 coins'
                        ]);
                    }

                    $totalBetAmount += $betAmount;

                    $validatedPredictions[] = [
                        'question' => $question,
                        'choice' => $choice,
                        'bet_amount' => $betAmount,
                    ];
                }

                // Check if user has sufficient coins for total bet
                if ($user->daily_coins < $totalBetAmount) {
                    throw ValidationException::withMessages([
                        'bet_amount' => 'Insufficient coins for bet'
                    ]);
                }

                // Second pass: create all predictions
                $createdPredictions = [];
                foreach ($validatedPredictions as $predictionData) {
                    $question = $predictionData['question'];
                    $choice = $predictionData['choice'];
                    $betAmount = $predictionData['bet_amount'];

                    // Calculate potential winnings with multipliers
                    $streakMultiplier = $this->streakService->getStreakMultiplier($user->current_streak);
                    $baseMultiplier = 1.5; // Base multiplier for predictions
                    $totalMultiplier = $baseMultiplier * $streakMultiplier;
                    $potentialWinnings = intval($betAmount * $totalMultiplier);

                    // Create prediction
                    $prediction = Prediction::create([
                        'user_id' => $user->id,
                        'question_id' => $question->id,
                        'choice' => $choice,
                        'bet_amount' => $betAmount,
                        'potential_winnings' => $potentialWinnings,
                        'multiplier_applied' => $streakMultiplier,
                        'is_correct' => null,
                        'actual_winnings' => null,
                    ]);

                    $createdPredictions[] = $prediction;

                    // Deduct coins from user
                    $user->daily_coins -= $betAmount;
                }

                // Save user with updated coins
                $user->save();

                // Determine redirect and success message
                $count = count($createdPredictions);
                $message = $count === 1 
                    ? 'Prediction submitted successfully!' 
                    : "All {$count} predictions submitted successfully!";

                return redirect()->route('dashboard')
                    ->with('success', $message);
            });

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to submit predictions. Please try again.')
                ->withInput();
        }
    }

    /**
     * Show prediction details for a specific question.
     */
    public function show(Request $request, PredictionQuestion $question)
    {
        $user = $request->user();
        
        $prediction = $question->predictions()
            ->where('user_id', $user->id)
            ->first();

        if (!$prediction) {
            return redirect()->route('questions.daily')
                ->with('error', 'No prediction found for this question.');
        }

        return response()->json([
            'prediction' => [
                'id' => $prediction->id,
                'choice' => $prediction->choice,
                'bet_amount' => $prediction->bet_amount,
                'potential_winnings' => $prediction->potential_winnings,
                'actual_winnings' => $prediction->actual_winnings,
                'is_correct' => $prediction->is_correct,
                'multiplier_applied' => $prediction->multiplier_applied,
                'created_at' => $prediction->created_at->toISOString(),
            ],
            'question' => [
                'id' => $question->id,
                'title' => $question->title,
                'option_a' => $question->option_a,
                'option_b' => $question->option_b,
                'status' => $question->status,
                'correct_answer' => $question->correct_answer,
                'resolution_time' => $question->resolution_time->toISOString(),
            ]
        ]);
    }
}