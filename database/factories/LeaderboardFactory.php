<?php

namespace Database\Factories;

use App\Models\ApiClient;
use App\Models\Leaderboard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Leaderboard> */
final class LeaderboardFactory extends Factory
{
    protected $model = Leaderboard::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'api_client_id' => ApiClient::factory(),
            'external_id' => 'board_'.Str::lower(Str::random(12)),
            'name' => fake()->words(2, true),
        ];
    }
}
