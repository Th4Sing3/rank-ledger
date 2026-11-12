<?php

namespace Database\Factories;

use App\Models\ApiClient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ApiClient> */
final class ApiClientFactory extends Factory
{
    protected $model = ApiClient::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => fake()->unique()->company(),
            'api_key_hash' => hash('sha256', Str::random(64)),
            'webhook_url' => null,
            'webhook_secret' => null,
        ];
    }
}
