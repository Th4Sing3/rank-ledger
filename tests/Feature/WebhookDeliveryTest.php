<?php

namespace Tests\Feature;

use App\Jobs\DeliverLeaderboardWebhook;
use App\Models\ApiClient;
use App\Models\OutboxMessage;
use App\Models\WebhookDelivery;
use App\Services\WebhookSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class WebhookDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_a_signed_body_with_a_stable_business_event_header(): void
    {
        $client = ApiClient::factory()->create([
            'webhook_url' => 'https://receiver.test/webhooks/leaderboard-events',
            'webhook_secret' => 'receiver-secret',
        ]);
        $message = OutboxMessage::query()->create([
            'api_client_id' => $client->getKey(),
            'topic' => 'leaderboard.score_recorded',
            'payload' => [
                'id' => 'event_001',
                'type' => 'leaderboard.score_recorded',
                'occurred_at' => '2026-11-11T18:00:00+00:00',
                'data' => [
                    'leaderboard_id' => 'daily-demo',
                    'participant_id' => 'player_001',
                    'points' => 125,
                    'score' => 125,
                ],
            ],
        ]);

        Http::fake([
            'https://receiver.test/*' => Http::response([], 204),
        ]);

        (new DeliverLeaderboardWebhook($message->getKey()))->handle(new WebhookSigner);

        Http::assertSent(function ($request) use ($message): bool {
            $signature = $request->header('X-Rank-Ledger-Signature')[0] ?? '';

            return $request->url() === 'https://receiver.test/webhooks/leaderboard-events'
                && $request->header('X-Rank-Ledger-Event')[0] === 'event_001'
                && $request->header('X-Rank-Ledger-Delivery')[0] !== ''
                && preg_match('/^t=\d+,v1=[a-f\d]{64}$/', $signature) === 1
                && json_decode($request->body(), true, flags: JSON_THROW_ON_ERROR) == $message->payload;
        });

        $this->assertDatabaseHas('webhook_deliveries', [
            'outbox_message_id' => $message->getKey(),
            'api_client_id' => $client->getKey(),
            'status' => 'delivered',
            'response_status' => 204,
        ]);
        $this->assertSame(1, WebhookDelivery::query()->count());
    }
}
