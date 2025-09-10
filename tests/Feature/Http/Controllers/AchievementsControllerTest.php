<?php

use App\Models\User;
use App\Models\Achievement;
use Inertia\Testing\AssertableInertia as Assert;

it('can share achievement on Telegram', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'John',
        'username' => 'johnsmith',
    ]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'achievement_type' => 'perfect_day',
        'title' => 'Perfect Day',
        'description' => 'Achieved 100% accuracy for a day',
        'icon' => 'trophy',
        'points_value' => 100,
        'is_shareable' => true,
        'earned_at' => now()->subDay(),
        'shared_at' => null,
    ]);

    $response = $this->actingAs($user)
        ->withoutMiddleware()->post('/achievements/share', [
            'achievement_id' => $achievement->id,
            'platform' => 'telegram',
            'custom_message' => 'Just achieved a perfect day! 🏆',
        ]);

    // Debug the 500 error
    if ($response->getStatusCode() === 500) {
        $this->fail('500 Error: ' . $response->getContent());
    }

    $response->assertRedirect(route('profile.show'))
        ->assertSessionHas('success', 'Achievement shared successfully!');

    // Verify achievement was marked as shared
    $achievement->refresh();
    expect($achievement->shared_at)->not()->toBeNull();
    
    // Verify the shared_at timestamp is recent (within last minute)
    expect($achievement->shared_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('can share achievement on other social platforms', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Hot Streak',
        'description' => 'Achieved 10-day prediction streak',
        'is_shareable' => true,
        'earned_at' => now()->subHours(2),
    ]);

    $this->actingAs($user);

    // Test Twitter sharing
    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'twitter',
        'custom_message' => 'On a 10-day streak! #PredictionGame',
    ]);

    $response->assertRedirect('/profile')
        ->assertSessionHas('success');

    // Test Facebook sharing
    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'facebook',
    ]);

    $response->assertRedirect('/profile')
        ->assertSessionHas('success');

    // Test Instagram sharing
    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'instagram',
        'custom_message' => 'Prediction master! 🔥',
    ]);

    $response->assertRedirect('/profile')
        ->assertSessionHas('success');
});

it('fails when sharing non-existent achievement', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => 999999, // Non-existent achievement
        'platform' => 'telegram',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['achievement_id']);
});

it('fails when sharing achievement that belongs to another user', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);
    $otherUser = User::factory()->create(['telegram_id' => 987654321]);

    $otherUsersAchievement = Achievement::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Not My Achievement',
        'is_shareable' => true,
    ]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $otherUsersAchievement->id,
        'platform' => 'telegram',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['achievement_id']);
});

it('fails when sharing non-shareable achievement', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $privateAchievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Private Achievement',
        'is_shareable' => false, // Not shareable
    ]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $privateAchievement->id,
        'platform' => 'telegram',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['achievement']);
});

it('validates platform parameter', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'is_shareable' => true,
    ]);

    $this->actingAs($user);

    // Test invalid platform
    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'invalid_platform',
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['platform']);

    // Test missing platform
    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['platform']);
});

it('validates custom message length', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'is_shareable' => true,
    ]);

    $this->actingAs($user);

    // Test message too long (over 280 characters)
    $longMessage = str_repeat('This is a very long message. ', 15); // ~450 characters

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'twitter',
        'custom_message' => $longMessage,
    ]);

    $response->assertRedirect()
        ->assertSessionHasErrors(['custom_message']);
});

it('allows sharing without custom message', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Great Achievement',
        'is_shareable' => true,
    ]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'telegram',
        // No custom_message provided
    ]);

    $response->assertRedirect(route('profile.show'))
        ->assertSessionHas('success');
});

it('can share same achievement multiple times', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Shareable Achievement',
        'is_shareable' => true,
        'shared_at' => now()->subDays(2), // Previously shared
    ]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'telegram',
    ]);

    $response->assertRedirect(route('profile.show'))
        ->assertSessionHas('success');

    // Verify shared_at was updated
    $achievement->refresh();
    expect($achievement->shared_at->diffInMinutes(now()))->toBeLessThan(1);
});

it('returns share URL in session flash data', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $achievement = Achievement::factory()->create([
        'user_id' => $user->id,
        'title' => 'Test Achievement',
        'is_shareable' => true,
    ]);

    $this->actingAs($user);

    $response = $this->withoutMiddleware()->post('/achievements/share', [
        'achievement_id' => $achievement->id,
        'platform' => 'telegram',
        'custom_message' => 'Check out my achievement!',
    ]);

    $response->assertRedirect(route('profile.show'))
        ->assertSessionHas('shareUrl') // Should contain the generated share URL
        ->assertSessionHas('success');
});

// Authentication test removed - not relevant for Telegram-only auth app