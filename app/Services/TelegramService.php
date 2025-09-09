<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Carbon\Carbon;

class TelegramService
{
    /**
     * Telegram Bot API base URL.
     */
    private string $apiUrl;

    /**
     * Bot token for API authentication.
     */
    private string $botToken;

    /**
     * WebApp URL for the game.
     */
    private string $webAppUrl;

    /**
     * Cache TTL for user data in seconds (1 hour).
     */
    public const CACHE_TTL = 3600;

    /**
     * Maximum retries for API calls.
     */
    public const MAX_RETRIES = 3;

    /**
     * Request timeout in seconds.
     */
    public const REQUEST_TIMEOUT = 10;

    public function __construct()
    {
        $this->botToken = config('telegram.bot_token');
        $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
        $this->webAppUrl = config('telegram.web_app_url', config('app.url'));
    }

    /**
     * Validate Telegram WebApp init data.
     */
    public function validateWebAppData(array $initData): array
    {
        $botToken = $this->botToken;
        $checkString = '';
        $hash = '';
        
        // Extract hash and build check string
        foreach ($initData as $key => $value) {
            if ($key === 'hash') {
                $hash = $value;
                continue;
            }
            
            if ($checkString !== '') {
                $checkString .= "\n";
            }
            
            $checkString .= "{$key}={$value}";
        }
        
        // Sort parameters alphabetically
        $pairs = explode("\n", $checkString);
        sort($pairs);
        $checkString = implode("\n", $pairs);
        
        // Calculate expected hash
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $expectedHash = hash_hmac('sha256', $checkString, $secretKey);
        
        if (!hash_equals($expectedHash, $hash)) {
            throw new \InvalidArgumentException('Invalid Telegram WebApp data');
        }
        
        // Parse user data
        $userData = json_decode($initData['user'] ?? '{}', true);
        
        // Check auth date (should not be older than 24 hours)
        $authDate = $initData['auth_date'] ?? 0;
        if (time() - $authDate > 86400) {
            throw new \InvalidArgumentException('Telegram WebApp data is too old');
        }
        
        return [
            'user' => $userData,
            'auth_date' => $authDate,
            'query_id' => $initData['query_id'] ?? null,
            'start_param' => $initData['start_param'] ?? null,
        ];
    }

    /**
     * Create or update user from Telegram data.
     */
    public function createOrUpdateUser(array $telegramData): User
    {
        $telegramUser = $telegramData['user'];
        $telegramId = $telegramUser['id'];
        
        $user = User::where('telegram_id', $telegramId)->first();
        
        if ($user) {
            // Update existing user
            $user->update([
                'name' => $telegramUser['first_name'] . ($telegramUser['last_name'] ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_language_code' => $telegramUser['language_code'] ?? 'en',
                'telegram_updated_at' => now(),
            ]);
            
            Log::info('Telegram user updated', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'username' => $telegramUser['username'] ?? null,
            ]);
        } else {
            // Create new user
            $user = User::create([
                'name' => $telegramUser['first_name'] . ($telegramUser['last_name'] ? ' ' . $telegramUser['last_name'] : ''),
                'telegram_id' => $telegramId,
                'telegram_username' => $telegramUser['username'] ?? null,
                'telegram_language_code' => $telegramUser['language_code'] ?? 'en',
                'avatar' => $this->getTelegramUserPhoto($telegramId),
                'daily_coins' => 1000, // Starting coins
                'telegram_created_at' => now(),
                'telegram_updated_at' => now(),
            ]);
            
            Log::info('New Telegram user created', [
                'user_id' => $user->id,
                'telegram_id' => $telegramId,
                'username' => $telegramUser['username'] ?? null,
                'name' => $user->name,
            ]);
        }
        
        // Clear user cache
        $this->clearUserCache($telegramId);
        
        return $user;
    }

    /**
     * Send message to Telegram user.
     */
    public function sendMessage(int $telegramId, string $text, array $options = []): array
    {
        $payload = array_merge([
            'chat_id' => $telegramId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);
        
        return $this->makeApiCall('sendMessage', $payload);
    }

    /**
     * Send game notification to user.
     */
    public function sendGameNotification(User $user, string $type, array $data = []): bool
    {
        if (!$user->telegram_id || !$user->notifications_enabled) {
            return false;
        }
        
        $message = $this->buildNotificationMessage($type, $data);
        
        if (!$message) {
            return false;
        }
        
        try {
            $response = $this->sendMessage($user->telegram_id, $message, [
                'reply_markup' => $this->getGameInlineKeyboard(),
            ]);
            
            Log::info('Game notification sent', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'notification_type' => $type,
                'success' => $response['ok'] ?? false,
            ]);
            
            return $response['ok'] ?? false;
        } catch (\Exception $e) {
            Log::error('Failed to send game notification', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
                'notification_type' => $type,
                'error' => $e->getMessage(),
            ]);
            
            return false;
        }
    }

    /**
     * Create share link for game results.
     */
    public function createShareLink(User $user, array $shareData): string
    {
        $shareText = $this->buildShareText($shareData);
        $webAppButton = urlencode("Play Now");
        
        $shareUrl = "https://t.me/share/url?" . http_build_query([
            'url' => $this->webAppUrl,
            'text' => $shareText,
        ]);
        
        return $shareUrl;
    }

    /**
     * Get Telegram user photo URL.
     */
    public function getTelegramUserPhoto(int $telegramId): ?string
    {
        $cacheKey = "telegram_photo:{$telegramId}";
        
        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($telegramId) {
            try {
                $response = $this->makeApiCall('getUserProfilePhotos', [
                    'user_id' => $telegramId,
                    'limit' => 1,
                ]);
                
                if (!$response['ok'] || empty($response['result']['photos'])) {
                    return null;
                }
                
                $photo = $response['result']['photos'][0];
                $fileId = end($photo)['file_id']; // Get largest photo
                
                $fileResponse = $this->makeApiCall('getFile', ['file_id' => $fileId]);
                
                if (!$fileResponse['ok']) {
                    return null;
                }
                
                $filePath = $fileResponse['result']['file_path'];
                return "https://api.telegram.org/file/bot{$this->botToken}/{$filePath}";
                
            } catch (\Exception $e) {
                Log::warning('Failed to get Telegram user photo', [
                    'telegram_id' => $telegramId,
                    'error' => $e->getMessage(),
                ]);
                
                return null;
            }
        });
    }

    /**
     * Set webhook for bot.
     */
    public function setWebhook(string $url, array $options = []): array
    {
        $payload = array_merge([
            'url' => $url,
            'max_connections' => 40,
            'allowed_updates' => ['message', 'callback_query', 'inline_query'],
        ], $options);
        
        return $this->makeApiCall('setWebhook', $payload);
    }

    /**
     * Delete webhook.
     */
    public function deleteWebhook(): array
    {
        return $this->makeApiCall('deleteWebhook');
    }

    /**
     * Get webhook info.
     */
    public function getWebhookInfo(): array
    {
        return $this->makeApiCall('getWebhookInfo');
    }

    /**
     * Handle webhook update.
     */
    public function handleWebhookUpdate(array $update): void
    {
        try {
            if (isset($update['message'])) {
                $this->handleMessage($update['message']);
            } elseif (isset($update['callback_query'])) {
                $this->handleCallbackQuery($update['callback_query']);
            } elseif (isset($update['inline_query'])) {
                $this->handleInlineQuery($update['inline_query']);
            }
        } catch (\Exception $e) {
            Log::error('Webhook update handling failed', [
                'update' => $update,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate deep link for the bot.
     */
    public function generateDeepLink(string $startParam = null): string
    {
        $botUsername = config('telegram.bot_username');
        $deepLink = "https://t.me/{$botUsername}";
        
        if ($startParam) {
            $deepLink .= "?start=" . urlencode($startParam);
        }
        
        return $deepLink;
    }

    /**
     * Get user statistics for sharing.
     */
    public function getUserSharingStats(User $user): array
    {
        $cacheKey = "user_share_stats:{$user->id}";
        
        return Cache::remember($cacheKey, 300, function () use ($user) {
            $predictions = $user->predictions()->whereNotNull('is_correct')->get();
            
            return [
                'total_predictions' => $predictions->count(),
                'correct_predictions' => $predictions->where('is_correct', true)->count(),
                'accuracy' => $predictions->count() > 0 
                    ? round(($predictions->where('is_correct', true)->count() / $predictions->count()) * 100, 1)
                    : 0,
                'current_streak' => $user->current_streak,
                'best_streak' => $user->best_streak,
                'total_winnings' => $predictions->sum('actual_winnings'),
                'rank' => $this->getUserRank($user),
            ];
        });
    }

    /**
     * Make API call to Telegram Bot API.
     */
    private function makeApiCall(string $method, array $params = []): array
    {
        $url = $this->apiUrl . $method;
        
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::timeout(self::REQUEST_TIMEOUT)
                    ->post($url, $params);
                
                if ($response->successful()) {
                    return $response->json();
                }
                
                if ($attempt === self::MAX_RETRIES) {
                    throw new \Exception("API call failed after {$attempt} attempts: " . $response->body());
                }
                
                sleep($attempt); // Exponential backoff
                
            } catch (\Exception $e) {
                if ($attempt === self::MAX_RETRIES) {
                    Log::error('Telegram API call failed', [
                        'method' => $method,
                        'params' => $params,
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    
                    throw $e;
                }
            }
        }
        
        return ['ok' => false, 'description' => 'Max retries exceeded'];
    }

    /**
     * Build notification message based on type.
     */
    private function buildNotificationMessage(string $type, array $data): ?string
    {
        return match ($type) {
            'daily_questions' => "🎯 <b>New Daily Questions Available!</b>\n\nFresh predictions are ready. Make your choices and win coins!",
            
            'streak_milestone' => "🔥 <b>Streak Milestone!</b>\n\nYou've reached a {$data['streak']} prediction streak! Your multiplier is now {$data['multiplier']}x",
            
            'achievement_earned' => "🏆 <b>Achievement Unlocked!</b>\n\n<b>{$data['name']}</b>\n{$data['description']}\n\nReward: {$data['reward_coins']} coins",
            
            'leaderboard_position' => "📊 <b>Leaderboard Update!</b>\n\nYou're now ranked #{$data['rank']} with {$data['score']} points!",
            
            'prediction_resolved' => $data['correct'] 
                ? "✅ <b>Correct Prediction!</b>\n\nYou won {$data['winnings']} coins on: {$data['question']}"
                : "❌ <b>Prediction Result</b>\n\nBetter luck next time on: {$data['question']}",
                
            'coins_low' => "💰 <b>Coins Running Low!</b>\n\nYou have {$data['coins']} coins left. Daily coins reset in {$data['reset_time']} hours.",
            
            'welcome' => "🎉 <b>Welcome to Guess!</b>\n\nMake predictions, win coins, and climb the leaderboard!\n\nYou start with 1,000 coins. Good luck!",
            
            default => null,
        };
    }

    /**
     * Get inline keyboard for game.
     */
    private function getGameInlineKeyboard(): array
    {
        return [
            'inline_keyboard' => [
                [
                    [
                        'text' => '🎮 Play Game',
                        'web_app' => ['url' => $this->webAppUrl],
                    ],
                ],
                [
                    [
                        'text' => '📊 Leaderboard',
                        'callback_data' => 'show_leaderboard',
                    ],
                    [
                        'text' => '🏆 Achievements',
                        'callback_data' => 'show_achievements',
                    ],
                ],
            ],
        ];
    }

    /**
     * Build share text for results.
     */
    private function buildShareText(array $shareData): string
    {
        $type = $shareData['type'] ?? 'general';
        
        return match ($type) {
            'streak' => "🔥 I'm on a {$shareData['streak']} prediction streak in Guess!\n\nCan you beat my accuracy? Join me and let's see who's the better predictor! 🎯",
            
            'achievement' => "🏆 Just unlocked '{$shareData['achievement']}' in Guess!\n\nJoin me in this exciting prediction game and see what achievements you can unlock! 🎮",
            
            'leaderboard' => "📊 I'm ranked #{$shareData['rank']} on the Guess leaderboard!\n\nThink you can beat my {$shareData['accuracy']}% accuracy? Challenge accepted! 🎯",
            
            'perfect_day' => "✨ Perfect day in Guess! Got all {$shareData['predictions']} predictions correct!\n\nCan you match my prediction skills? Let's find out! 🎯",
            
            default => "🎯 Playing Guess - the ultimate prediction game!\n\nJoin me and test your forecasting skills. Win coins, unlock achievements, and climb the leaderboard! 🏆",
        };
    }

    /**
     * Handle incoming message.
     */
    private function handleMessage(array $message): void
    {
        $telegramId = $message['from']['id'];
        $text = $message['text'] ?? '';
        
        if (str_starts_with($text, '/start')) {
            $this->handleStartCommand($telegramId, $message['from'], $text);
        } elseif ($text === '/stats') {
            $this->handleStatsCommand($telegramId);
        } elseif ($text === '/help') {
            $this->handleHelpCommand($telegramId);
        }
    }

    /**
     * Handle callback query.
     */
    private function handleCallbackQuery(array $callbackQuery): void
    {
        $telegramId = $callbackQuery['from']['id'];
        $data = $callbackQuery['data'];
        
        match ($data) {
            'show_leaderboard' => $this->sendLeaderboard($telegramId),
            'show_achievements' => $this->sendAchievements($telegramId),
            default => null,
        };
        
        // Answer callback query
        $this->makeApiCall('answerCallbackQuery', [
            'callback_query_id' => $callbackQuery['id'],
        ]);
    }

    /**
     * Handle inline query.
     */
    private function handleInlineQuery(array $inlineQuery): void
    {
        $query = $inlineQuery['query'];
        $results = [];
        
        // Add share result
        $results[] = [
            'type' => 'article',
            'id' => 'share_game',
            'title' => 'Share Guess Game',
            'description' => 'Invite friends to play Guess',
            'input_message_content' => [
                'message_text' => "🎯 Check out Guess - the ultimate prediction game!\n\nTest your forecasting skills, win coins, and compete with friends! 🏆",
            ],
            'reply_markup' => $this->getGameInlineKeyboard(),
        ];
        
        $this->makeApiCall('answerInlineQuery', [
            'inline_query_id' => $inlineQuery['id'],
            'results' => $results,
            'cache_time' => 300,
        ]);
    }

    /**
     * Handle /start command.
     */
    private function handleStartCommand(int $telegramId, array $from, string $text): void
    {
        $startParam = null;
        if (preg_match('/\/start (.+)/', $text, $matches)) {
            $startParam = $matches[1];
        }
        
        $message = "🎉 <b>Welcome to Guess!</b>\n\n" .
                  "🎯 Make predictions on daily questions\n" .
                  "💰 Win coins for correct answers\n" .
                  "🔥 Build streaks for bonus multipliers\n" .
                  "🏆 Unlock achievements and climb the leaderboard\n\n" .
                  "Ready to test your prediction skills?";
        
        $this->sendMessage($telegramId, $message, [
            'reply_markup' => $this->getGameInlineKeyboard(),
        ]);
    }

    /**
     * Handle /stats command.
     */
    private function handleStatsCommand(int $telegramId): void
    {
        $user = User::where('telegram_id', $telegramId)->first();
        
        if (!$user) {
            $this->sendMessage($telegramId, "Please start the game first using the button below!", [
                'reply_markup' => $this->getGameInlineKeyboard(),
            ]);
            return;
        }
        
        $stats = $this->getUserSharingStats($user);
        
        $message = "📊 <b>Your Stats</b>\n\n" .
                  "🎯 Predictions: {$stats['total_predictions']}\n" .
                  "✅ Correct: {$stats['correct_predictions']}\n" .
                  "📈 Accuracy: {$stats['accuracy']}%\n" .
                  "🔥 Current Streak: {$stats['current_streak']}\n" .
                  "🏆 Best Streak: {$stats['best_streak']}\n" .
                  "💰 Total Winnings: {$stats['total_winnings']} coins\n" .
                  "📊 Rank: #{$stats['rank']}";
        
        $this->sendMessage($telegramId, $message, [
            'reply_markup' => $this->getGameInlineKeyboard(),
        ]);
    }

    /**
     * Handle /help command.
     */
    private function handleHelpCommand(int $telegramId): void
    {
        $message = "❓ <b>How to Play Guess</b>\n\n" .
                  "1️⃣ Open the game using the button below\n" .
                  "2️⃣ Make predictions on daily questions\n" .
                  "3️⃣ Bet your coins on your predictions\n" .
                  "4️⃣ Win coins for correct answers\n" .
                  "5️⃣ Build streaks for bonus multipliers\n\n" .
                  "<b>Features:</b>\n" .
                  "🎯 Daily prediction questions\n" .
                  "💰 Coin betting system\n" .
                  "🔥 Streak multipliers\n" .
                  "🏆 Achievement system\n" .
                  "📊 Global leaderboards\n\n" .
                  "<b>Commands:</b>\n" .
                  "/start - Start the game\n" .
                  "/stats - View your statistics\n" .
                  "/help - Show this help message";
        
        $this->sendMessage($telegramId, $message, [
            'reply_markup' => $this->getGameInlineKeyboard(),
        ]);
    }

    /**
     * Send leaderboard information.
     */
    private function sendLeaderboard(int $telegramId): void
    {
        // This would integrate with LeaderboardService
        $message = "📊 <b>Leaderboard</b>\n\nView the full leaderboard in the game!";
        
        $this->sendMessage($telegramId, $message, [
            'reply_markup' => $this->getGameInlineKeyboard(),
        ]);
    }

    /**
     * Send achievements information.
     */
    private function sendAchievements(int $telegramId): void
    {
        // This would integrate with AchievementService
        $message = "🏆 <b>Achievements</b>\n\nView all achievements and your progress in the game!";
        
        $this->sendMessage($telegramId, $message, [
            'reply_markup' => $this->getGameInlineKeyboard(),
        ]);
    }

    /**
     * Get user rank (simplified for caching).
     */
    private function getUserRank(User $user): int
    {
        return User::where('id', '!=', $user->id)
            ->where(function ($query) use ($user) {
                $query->where('current_streak', '>', $user->current_streak)
                      ->orWhere(function ($q) use ($user) {
                          $q->where('current_streak', $user->current_streak)
                            ->where('best_streak', '>', $user->best_streak);
                      });
            })
            ->count() + 1;
    }

    /**
     * Clear user cache.
     */
    private function clearUserCache(int $telegramId): void
    {
        Cache::forget("telegram_photo:{$telegramId}");
        
        $user = User::where('telegram_id', $telegramId)->first();
        if ($user) {
            Cache::forget("user_share_stats:{$user->id}");
        }
    }
}