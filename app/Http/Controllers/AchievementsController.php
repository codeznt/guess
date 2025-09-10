<?php

namespace App\Http\Controllers;

use App\Models\Achievement;
use App\Services\AchievementService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AchievementsController extends Controller
{
    public function __construct(
        protected AchievementService $achievementService,
        protected TelegramService $telegramService
    ) {}

    /**
     * Share an achievement on social platforms.
     */
    public function share(Request $request)
    {
        $user = $request->user();

        // Validate the request
        $validator = Validator::make($request->all(), [
            'achievement_id' => 'required|integer|exists:achievements,id',
            'platform' => 'required|in:telegram,twitter,facebook,instagram,linkedin,general',
            'custom_message' => 'nullable|string|max:280',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        try {
            $achievementId = $request->input('achievement_id');
            $platform = $request->input('platform');
            $customMessage = $request->input('custom_message');

            // Find the achievement and verify ownership
            $achievement = Achievement::where('id', $achievementId)
                ->where('user_id', $user->id)
                ->first();

            if (!$achievement) {
                throw ValidationException::withMessages([
                    'achievement_id' => 'Achievement not found'
                ]);
            }

            // Check if achievement is shareable
            if (!$achievement->is_shareable) {
                throw ValidationException::withMessages([
                    'achievement' => 'This achievement cannot be shared'
                ]);
            }

            // Generate the share content
            $shareContent = $this->generateShareContent($achievement, $customMessage, $user);
            
            // Generate share URL based on platform
            $shareUrl = $this->generateShareUrl($platform, $shareContent, $achievement);

            // Mark achievement as shared (update timestamp)
            $achievement->update([
                'shared_at' => now(),
                'share_count' => $achievement->share_count + 1,
            ]);

            // Log the share activity for analytics
            $this->achievementService->logShareActivity($user, $achievement, $platform);

            // Handle platform-specific sharing logic
            $result = $this->handlePlatformShare($platform, $shareContent, $shareUrl, $user, $achievement);

            return redirect()->route('profile')
                ->with('success', 'Achievement shared successfully!')
                ->with('shareUrl', $shareUrl)
                ->with('shareContent', $shareContent)
                ->with('platform', $platform);

        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            return back()
                ->with('error', 'Failed to share achievement. Please try again.')
                ->withInput();
        }
    }

    /**
     * Generate share content based on achievement and custom message.
     */
    protected function generateShareContent(Achievement $achievement, ?string $customMessage, $user): array
    {
        $baseMessage = $customMessage ?: "Just earned the '{$achievement->title}' achievement! 🎉";
        
        $defaultContent = [
            'title' => $achievement->title,
            'description' => $achievement->description,
            'message' => $baseMessage,
            'hashtags' => ['PredictionGame', 'Achievement', 'Gaming'],
            'user' => [
                'name' => $user->first_name . ($user->last_name ? ' ' . $user->last_name : ''),
                'username' => $user->username,
            ],
            'achievement' => [
                'icon' => $achievement->icon,
                'points' => $achievement->points_value,
                'type' => $achievement->achievement_type,
                'earned_at' => $achievement->earned_at->format('M j, Y'),
            ],
            'app_url' => config('app.url'),
        ];

        // Add platform-specific formatting
        return $defaultContent;
    }

    /**
     * Generate platform-specific share URL.
     */
    protected function generateShareUrl(string $platform, array $content, Achievement $achievement): string
    {
        $appUrl = config('app.url');
        $achievementUrl = "{$appUrl}/achievements/{$achievement->id}";
        $message = urlencode($content['message']);
        $hashtags = implode(',', $content['hashtags']);

        return match ($platform) {
            'telegram' => "https://t.me/share/url?url={$achievementUrl}&text={$message}",
            'twitter' => "https://twitter.com/intent/tweet?text={$message}&url={$achievementUrl}&hashtags={$hashtags}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$achievementUrl}&quote={$message}",
            'instagram' => $achievementUrl, // Instagram doesn't support direct URL sharing
            'linkedin' => "https://www.linkedin.com/sharing/share-offsite/?url={$achievementUrl}&title=" . urlencode($content['title']) . "&summary={$message}",
            default => $achievementUrl,
        };
    }

    /**
     * Handle platform-specific sharing logic.
     */
    protected function handlePlatformShare(string $platform, array $content, string $shareUrl, $user, Achievement $achievement): array
    {
        $result = [
            'success' => true,
            'platform' => $platform,
            'url' => $shareUrl,
        ];

        try {
            switch ($platform) {
                case 'telegram':
                    // For Telegram WebApp users, we can integrate with Telegram's sharing API
                    if ($user->telegram_id) {
                        $result['telegram_data'] = $this->telegramService->generateShareData(
                            $content['message'],
                            $shareUrl,
                            $achievement
                        );
                    }
                    break;

                case 'twitter':
                    // Could integrate with Twitter API for auto-posting if needed
                    break;

                case 'facebook':
                    // Could integrate with Facebook API for auto-posting if needed
                    break;

                case 'instagram':
                    // Instagram requires special handling for image sharing
                    $result['requires_image'] = true;
                    $result['suggested_text'] = $content['message'];
                    break;

                case 'linkedin':
                    // Could integrate with LinkedIn API for auto-posting if needed
                    break;
            }

        } catch (\Exception $e) {
            // Log error but don't fail the sharing process
            logger()->error("Platform sharing error for {$platform}", [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'achievement_id' => $achievement->id,
            ]);
        }

        return $result;
    }

    /**
     * Show public achievement page (for shared links).
     */
    public function show(Achievement $achievement)
    {
        // This would show a public page for shared achievements
        $achievementData = [
            'id' => $achievement->id,
            'title' => $achievement->title,
            'description' => $achievement->description,
            'icon' => $achievement->icon,
            'points_value' => $achievement->points_value,
            'earned_at' => $achievement->earned_at->format('M j, Y g:i A'),
            'user' => [
                'first_name' => $achievement->user->first_name,
                'username' => $achievement->user->username,
            ],
        ];

        return response()->json($achievementData);
    }

    /**
     * Get user's achievements with sharing status.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $achievements = Achievement::where('user_id', $user->id)
            ->orderBy('earned_at', 'desc')
            ->get()
            ->map(function ($achievement) {
                return [
                    'id' => $achievement->id,
                    'title' => $achievement->title,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                    'points_value' => $achievement->points_value,
                    'achievement_type' => $achievement->achievement_type,
                    'is_shareable' => $achievement->is_shareable,
                    'earned_at' => $achievement->earned_at->toISOString(),
                    'shared_at' => $achievement->shared_at?->toISOString(),
                    'share_count' => $achievement->share_count ?? 0,
                    'can_share' => $achievement->is_shareable,
                ];
            });

        return response()->json([
            'achievements' => $achievements->values(),
            'total' => $achievements->count(),
            'shareable_count' => $achievements->where('is_shareable', true)->count(),
        ]);
    }
}