<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class TelegramAuthController extends Controller
{
    /**
     * Handle Telegram WebApp authentication.
     */
    public function authenticate(Request $request)
    {
        try {
            $request->validate([
                'telegram_user' => 'required|array',
                'telegram_user.id' => 'required|integer',
                'telegram_user.first_name' => 'required|string|max:255',
                'telegram_user.last_name' => 'nullable|string|max:255',
                'telegram_user.username' => 'nullable|string|max:255',
                'telegram_user.language_code' => 'nullable|string|max:10',
                'telegram_user.is_premium' => 'nullable|boolean',
                'hash' => 'required|string',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $e->errors()
            ], 422);
        }

        // In a real implementation, you would validate the hash against Telegram's bot token
        // For now, we'll accept 'mock_hash_validation' for testing
        if ($request->hash !== 'mock_hash_validation') {
            return response()->json(['error' => 'Invalid Telegram authentication'], 401);
        }

        $telegramData = $request->telegram_user;
        
        // Find or create user based on Telegram ID
        $user = User::byTelegramId($telegramData['id'])->first();
        
        if ($user) {
            // Update existing user with latest Telegram data
            $updateData = [
                'first_name' => $telegramData['first_name'],
                'last_name' => $telegramData['last_name'] ?? null,
                'username' => $telegramData['username'] ?? null,
            ];
            
            // Only update last_active_date if it's a different day
            if ($user->last_active_date !== now()->toDateString()) {
                $updateData['last_active_date'] = now()->toDateString();
            }
            
            $user->update($updateData);
        } else {
            // Create new user
            $displayName = $telegramData['first_name'];
            if (isset($telegramData['last_name'])) {
                $displayName .= ' ' . $telegramData['last_name'];
            }
            
            $user = User::create([
                'name' => $displayName, // Required field
                'telegram_id' => $telegramData['id'],
                'first_name' => $telegramData['first_name'],
                'last_name' => $telegramData['last_name'] ?? null,
                'username' => $telegramData['username'] ?? null,
                'daily_coins' => 1000, // Default daily coins
                'total_predictions' => 0,
                'correct_predictions' => 0,
                'current_streak' => 0,
                'best_streak' => 0,
                'last_active_date' => now()->toDateString(),
            ]);
        }

        // Log the user in
        Auth::login($user);

        // Redirect to intended URL or dashboard
        return redirect()->intended('/dashboard');
    }
}
