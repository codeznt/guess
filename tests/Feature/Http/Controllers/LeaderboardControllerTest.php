<?php

use App\Models\User;
use App\Models\DailyLeaderboard;
use Inertia\Testing\AssertableInertia as Assert;

it('can get daily leaderboard rankings', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Current User',
    ]);

    // Create leaderboard entries for today
    $topUser = User::factory()->create([
        'telegram_id' => 987654321,
        'first_name' => 'Top Player',
        'username' => 'topplayer',
    ]);

    $secondUser = User::factory()->create([
        'telegram_id' => 555666777,
        'first_name' => 'Second Player',
        'username' => 'second',
    ]);

    $today = now()->toDateString();

    // Create leaderboard entries
    DailyLeaderboard::factory()->create([
        'user_id' => $topUser->id,
        'leaderboard_date' => $today,
        'total_winnings' => 2500,
        'predictions_made' => 12,
        'correct_predictions' => 10,
        'accuracy_percentage' => 83.33,
        'rank' => 1,
    ]);

    DailyLeaderboard::factory()->create([
        'user_id' => $secondUser->id,
        'leaderboard_date' => $today,
        'total_winnings' => 1800,
        'predictions_made' => 10,
        'correct_predictions' => 7,
        'accuracy_percentage' => 70.00,
        'rank' => 2,
    ]);

    DailyLeaderboard::factory()->create([
        'user_id' => $user->id,
        'leaderboard_date' => $today,
        'total_winnings' => 950,
        'predictions_made' => 8,
        'correct_predictions' => 5,
        'accuracy_percentage' => 62.50,
        'rank' => 27,
    ]);

    $this->actingAs($user);

    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 3)
            ->has('rankings.0', fn (Assert $entry) => $entry
                ->where('rank', 1)
                ->where('user.first_name', 'Top Player')
                ->where('user.username', 'topplayer')
                ->where('total_winnings', 2500)
                ->where('predictions_made', 12)
                ->where('correct_predictions', 10)
                ->where('accuracy_percentage', 83.33)
            )
            ->where('userRank', 27)
            ->where('totalParticipants', 3)
            ->has('meta', fn (Assert $meta) => $meta
                ->has('date')
                ->has('lastUpdated')
            )
        );
});

it('can get leaderboard for specific date', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $yesterdayUser = User::factory()->create([
        'telegram_id' => 987654321,
        'first_name' => 'Yesterday Winner',
    ]);

    $yesterday = now()->subDay()->toDateString();

    DailyLeaderboard::factory()->create([
        'user_id' => $yesterdayUser->id,
        'leaderboard_date' => $yesterday,
        'total_winnings' => 3000,
        'predictions_made' => 15,
        'correct_predictions' => 12,
        'accuracy_percentage' => 80.00,
        'rank' => 1,
    ]);

    $this->actingAs($user);

    $response = $this->get('/leaderboard?date=' . $yesterday);

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 1)
            ->where('rankings.0.user.first_name', 'Yesterday Winner')
            ->where('rankings.0.total_winnings', 3000)
        );
});

it('can limit number of leaderboard entries', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $today = now()->toDateString();

    // Create 20 leaderboard entries
    DailyLeaderboard::factory()->count(20)->create([
        'leaderboard_date' => $today,
        'rank' => function (array $attributes) {
            return DailyLeaderboard::where('leaderboard_date', $attributes['leaderboard_date'])->count() + 1;
        },
    ]);

    $this->actingAs($user);

    $response = $this->get('/leaderboard?limit=10');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 10) // Limited to 10
        );
});

it('returns empty leaderboard when no entries exist', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $this->actingAs($user);

    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 0)
            ->where('userRank', null)
            ->where('totalParticipants', 0)
        );
});

it('shows user rank when user is not in top rankings', function () {
    $user = User::factory()->create([
        'telegram_id' => 123456789,
        'first_name' => 'Low Rank User',
    ]);

    $today = now()->toDateString();

    // Create 60 leaderboard entries (default limit is 50)
    for ($i = 1; $i <= 60; $i++) {
        $entryUser = ($i === 55) ? $user : User::factory()->create();
        
        DailyLeaderboard::factory()->create([
            'user_id' => $entryUser->id,
            'leaderboard_date' => $today,
            'total_winnings' => 2000 - ($i * 30), // Decreasing winnings
            'rank' => $i,
        ]);
    }

    $this->actingAs($user);

    $response = $this->get('/leaderboard?limit=50');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 50) // Top 50 only
            ->where('userRank', 55) // User's actual rank
            ->where('totalParticipants', 60)
        );
});

it('orders leaderboard by rank correctly', function () {
    $user = User::factory()->create(['telegram_id' => 123456789]);

    $today = now()->toDateString();

    $users = User::factory()->count(5)->create();

    // Create leaderboard entries with mixed order insertion
    DailyLeaderboard::factory()->create([
        'user_id' => $users[2]->id,
        'leaderboard_date' => $today,
        'total_winnings' => 1000,
        'rank' => 3,
    ]);

    DailyLeaderboard::factory()->create([
        'user_id' => $users[0]->id,
        'leaderboard_date' => $today,
        'total_winnings' => 3000,
        'rank' => 1,
    ]);

    DailyLeaderboard::factory()->create([
        'user_id' => $users[1]->id,
        'leaderboard_date' => $today,
        'total_winnings' => 2000,
        'rank' => 2,
    ]);

    $this->actingAs($user);

    $response = $this->get('/leaderboard');

    $response->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Leaderboard/Daily')
            ->has('rankings', 3)
            ->where('rankings.0.rank', 1)
            ->where('rankings.0.total_winnings', 3000)
            ->where('rankings.1.rank', 2)
            ->where('rankings.1.total_winnings', 2000)
            ->where('rankings.2.rank', 3)
            ->where('rankings.2.total_winnings', 1000)
        );
});

it('requires authentication to view leaderboard', function () {
    $response = $this->get('/leaderboard');

    $response->assertRedirect('/login');
});