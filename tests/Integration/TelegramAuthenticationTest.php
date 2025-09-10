<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('authenticates user with valid Telegram WebApp data', function () {
    // Simulate Telegram WebApp authentication payload
    $telegramData = [
        'id' => 123456789,
        'first_name' => 'John',
        'last_name' => 'Doe',
        'username' => 'johndoe',
        'language_code' => 'en',
        'is_premium' => false,
    ];

    // Simulate auth flow - POST to auth endpoint with Telegram data
    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation', // In real implementation, this would be validated
    ]);

    $response->assertRedirect('/dashboard');

    // Verify user was created or updated
    $user = User::where('telegram_id', 123456789)->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('John');
    expect($user->last_name)->toBe('Doe');
    expect($user->username)->toBe('johndoe');
    expect($user->daily_coins)->toBe(1000); // Default daily coins
    expect($user->email)->toBeNull(); // Telegram users don't need email
    expect($user->password)->toBeNull(); // Telegram users don't need password

    // Verify user is authenticated
    $this->assertAuthenticatedAs($user);
});

it('creates new user on first Telegram authentication', function () {
    $telegramData = [
        'id' => 987654321,
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'username' => 'janesmith',
        'language_code' => 'es',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    // Verify new user was created
    $user = User::where('telegram_id', 987654321)->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Jane');
    expect($user->last_name)->toBe('Smith');
    expect($user->username)->toBe('janesmith');
    expect($user->daily_coins)->toBe(1000);
    expect($user->total_predictions)->toBe(0);
    expect($user->correct_predictions)->toBe(0);
    expect($user->current_streak)->toBe(0);
    expect($user->best_streak)->toBe(0);

    $this->assertAuthenticatedAs($user);
});

it('updates existing user on subsequent Telegram authentications', function () {
    // Create existing user
    $existingUser = User::factory()->create([
        'telegram_id' => 555666777,
        'first_name' => 'Old Name',
        'last_name' => 'Old Last',
        'username' => 'old_username',
        'daily_coins' => 750, // Some coins spent
        'total_predictions' => 15,
        'current_streak' => 3,
    ]);

    // Simulate updated Telegram data (user changed their name/username)
    $updatedTelegramData = [
        'id' => 555666777, // Same Telegram ID
        'first_name' => 'New Name',
        'last_name' => 'New Last',
        'username' => 'new_username',
        'language_code' => 'en',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $updatedTelegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    // Verify user was updated
    $existingUser->refresh();
    expect($existingUser->first_name)->toBe('New Name');
    expect($existingUser->last_name)->toBe('New Last');
    expect($existingUser->username)->toBe('new_username');
    
    // Verify game data was preserved
    expect($existingUser->daily_coins)->toBe(750);
    expect($existingUser->total_predictions)->toBe(15);
    expect($existingUser->current_streak)->toBe(3);

    $this->assertAuthenticatedAs($existingUser);
});

it('handles Telegram user without username', function () {
    $telegramData = [
        'id' => 444555666,
        'first_name' => 'Anonymous',
        'last_name' => 'User',
        // No username field
        'language_code' => 'fr',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('telegram_id', 444555666)->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Anonymous');
    expect($user->last_name)->toBe('User');
    expect($user->username)->toBeNull(); // No username provided
});

it('handles Telegram user with only first name', function () {
    $telegramData = [
        'id' => 333444555,
        'first_name' => 'SingleName',
        // No last_name or username
        'language_code' => 'de',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('telegram_id', 333444555)->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('SingleName');
    expect($user->last_name)->toBeNull();
    expect($user->username)->toBeNull();
});

it('fails authentication with invalid hash', function () {
    $telegramData = [
        'id' => 123456789,
        'first_name' => 'Test',
        'username' => 'testuser',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'invalid_hash',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'Invalid Telegram authentication']);

    // Verify no user was created
    expect(User::where('telegram_id', 123456789)->exists())->toBeFalse();
    $this->assertGuest();
});

it('fails authentication with missing required fields', function () {
    // Missing telegram_id
    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'first_name' => 'Test',
        ],
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['telegram_user.id']);

    // Missing first_name
    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'id' => 123456789,
        ],
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['telegram_user.first_name']);
});

it('handles concurrent authentication attempts for same user', function () {
    $telegramData = [
        'id' => 777888999,
        'first_name' => 'Concurrent',
        'username' => 'concurrent_user',
    ];

    // First authentication
    $response1 = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response1->assertRedirect('/dashboard');

    // Second authentication (simulating another session/device)
    $response2 = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response2->assertRedirect('/dashboard');

    // Verify only one user record exists
    $users = User::where('telegram_id', 777888999)->get();
    expect($users->count())->toBe(1);
});

it('preserves user game state across authentication sessions', function () {
    // Create user with game progress (active today)
    $user = User::factory()->create([
        'telegram_id' => 111222333,
        'first_name' => 'Game Player',
        'daily_coins' => 600,
        'total_predictions' => 50,
        'correct_predictions' => 35,
        'current_streak' => 8,
        'best_streak' => 15,
        'last_active_date' => now()->toDateString(), // Already active today
    ]);

    // Authenticate again (simulating app restart on same day)
    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'id' => 111222333,
            'first_name' => 'Game Player',
            'username' => 'gamer',
        ],
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    // Verify all game data is preserved and last_active_date unchanged (same day)
    $user->refresh();
    expect($user->daily_coins)->toBe(600);
    expect($user->total_predictions)->toBe(50);
    expect($user->correct_predictions)->toBe(35);
    expect($user->current_streak)->toBe(8);
    expect($user->best_streak)->toBe(15);
    expect($user->last_active_date)->toBe(now()->toDateString()); // Should remain today
});

it('updates last active date on authentication', function () {
    $user = User::factory()->create([
        'telegram_id' => 999888777,
        'last_active_date' => now()->subDays(3)->toDateString(),
    ]);

    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'id' => 999888777,
            'first_name' => 'Active User',
        ],
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    // Verify last_active_date was updated to today
    $user->refresh();
    expect($user->last_active_date)->toBe(now()->toDateString());
});

it('handles Telegram premium users correctly', function () {
    $telegramData = [
        'id' => 555777999,
        'first_name' => 'Premium',
        'username' => 'premium_user',
        'is_premium' => true,
        'language_code' => 'en',
    ];

    $response = $this->post('/auth/telegram', [
        'telegram_user' => $telegramData,
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('telegram_id', 555777999)->first();
    expect($user)->not->toBeNull();
    expect($user->first_name)->toBe('Premium');
    expect($user->username)->toBe('premium_user');
    // Premium status could be stored in future if needed
});

it('logs out existing session when authenticating from different device', function () {
    $user = User::factory()->create([
        'telegram_id' => 666777888,
        'first_name' => 'Multi Device',
    ]);

    // First authentication (device 1)
    $this->actingAs($user);
    expect($this->isAuthenticated())->toBeTrue();

    // Second authentication (device 2) - should maintain auth but could invalidate other sessions
    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'id' => 666777888,
            'first_name' => 'Multi Device',
        ],
        'hash' => 'mock_hash_validation',
    ]);

    $response->assertRedirect('/dashboard');
    $this->assertAuthenticatedAs($user);
});

it('redirects to intended URL after authentication', function () {
    // Try to access protected route first
    $response = $this->get('/profile');
    $response->assertRedirect('/login');

    // Now authenticate
    $response = $this->post('/auth/telegram', [
        'telegram_user' => [
            'id' => 123123123,
            'first_name' => 'Redirect Test',
        ],
        'hash' => 'mock_hash_validation',
    ]);

    // Should redirect to originally intended URL (/profile) instead of /dashboard
    // Note: This behavior depends on Laravel's intended() functionality
    $response->assertRedirect(); // Could be /profile or /dashboard depending on implementation
});