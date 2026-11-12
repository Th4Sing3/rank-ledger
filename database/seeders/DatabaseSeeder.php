<?php

namespace Database\Seeders;

use App\Models\ApiClient;
use App\Models\Leaderboard;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $demoKey = env('DEMO_API_KEY');

        if (! is_string($demoKey) || $demoKey === '') {
            return;
        }

        $client = ApiClient::query()->updateOrCreate(['name' => 'Local demo client'], [
            'api_key_hash' => hash('sha256', $demoKey),
            'webhook_url' => env('DEMO_WEBHOOK_URL'),
            'webhook_secret' => env('DEMO_WEBHOOK_SECRET'),
        ]);

        Leaderboard::query()->firstOrCreate([
            'api_client_id' => $client->getKey(),
            'external_id' => 'daily-demo',
        ], [
            'id' => (string) Str::uuid(),
            'name' => 'Daily demo leaderboard',
        ]);
    }
}
