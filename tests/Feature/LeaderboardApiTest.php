<?php

namespace Tests\Feature;

use App\Jobs\DispatchPendingOutboxMessages;
use App\Models\ApiClient;
use App\Models\Leaderboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class LeaderboardApiTest extends TestCase
{
    use RefreshDatabase;

    private ApiClient $client;

    private Leaderboard $leaderboard;

    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->apiKey = 'rk_test_'.str_repeat('a', 32);
        $this->client = ApiClient::factory()->create([
            'api_key_hash' => hash('sha256', $this->apiKey),
        ]);
        $this->leaderboard = Leaderboard::factory()
            ->for($this->client, 'apiClient')
            ->create(['external_id' => 'daily-demo']);
    }

    public function test_an_api_key_is_required(): void
    {
        $this->postJson($this->eventsUrl(), $this->payload())
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Authentication required.']);
    }

    public function test_it_validates_the_http_contract_before_writing(): void
    {
        $this->postJson($this->eventsUrl(), [
            'event_id' => 'event_001',
            'participant_id' => 'player_001',
            'points' => 0,
            'occurred_at' => 'not-a-date',
        ], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'idempotency_key',
                'points',
                'occurred_at',
            ]);

        $this->assertDatabaseCount('score_events', 0);
        $this->assertDatabaseCount('idempotency_keys', 0);
    }

    public function test_it_records_an_event_updates_the_projection_and_queues_the_outbox_dispatch(): void
    {
        $response = $this->postJson(
            $this->eventsUrl(),
            $this->payload(['points' => 125]),
            $this->headers('request_001'),
        );

        $response
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'false')
            ->assertJsonPath('change_status', 'created')
            ->assertJsonPath('event.id', 'event_001')
            ->assertJsonPath('entry.score', 125)
            ->assertJsonPath('entry.event_count', 1);

        $this->assertDatabaseHas('score_events', [
            'leaderboard_id' => $this->leaderboard->getKey(),
            'external_event_id' => 'event_001',
            'participant_id' => 'player_001',
            'points' => 125,
        ]);
        $this->assertDatabaseHas('leaderboard_entries', [
            'leaderboard_id' => $this->leaderboard->getKey(),
            'participant_id' => 'player_001',
            'score' => 125,
            'event_count' => 1,
        ]);
        $this->assertDatabaseCount('outbox_messages', 1);
        Queue::assertPushed(DispatchPendingOutboxMessages::class);
    }

    public function test_a_retry_with_the_same_idempotency_key_replays_the_original_response_without_double_counting(): void
    {
        $payload = $this->payload(['points' => 70]);
        $headers = $this->headers('request_retry');

        $first = $this->postJson($this->eventsUrl(), $payload, $headers)
            ->assertCreated();
        $second = $this->postJson($this->eventsUrl(), $payload, $headers)
            ->assertCreated()
            ->assertHeader('Idempotent-Replay', 'true');

        $this->assertSame($first->json(), $second->json());
        $this->assertDatabaseCount('score_events', 1);
        $this->assertDatabaseCount('outbox_messages', 1);
        $this->assertDatabaseHas('leaderboard_entries', [
            'leaderboard_id' => $this->leaderboard->getKey(),
            'participant_id' => 'player_001',
            'score' => 70,
            'event_count' => 1,
        ]);
        Queue::assertPushed(DispatchPendingOutboxMessages::class, 1);
    }

    public function test_a_reused_idempotency_key_with_a_different_payload_is_rejected(): void
    {
        $headers = $this->headers('request_conflict');

        $this->postJson($this->eventsUrl(), $this->payload(['points' => 50]), $headers)
            ->assertCreated();

        $this->postJson($this->eventsUrl(), $this->payload(['points' => 51]), $headers)
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This Idempotency-Key was already used with a different request.',
            ]);

        $this->assertDatabaseCount('score_events', 1);
        $this->assertDatabaseHas('leaderboard_entries', [
            'leaderboard_id' => $this->leaderboard->getKey(),
            'participant_id' => 'player_001',
            'score' => 50,
        ]);
    }

    public function test_a_reused_business_event_id_with_different_data_is_rejected(): void
    {
        $this->postJson(
            $this->eventsUrl(),
            $this->payload(['points' => 50]),
            $this->headers('request_first_event'),
        )->assertCreated();

        $this->postJson(
            $this->eventsUrl(),
            $this->payload(['points' => 51]),
            $this->headers('request_second_event'),
        )
            ->assertConflict()
            ->assertExactJson([
                'message' => 'This event_id already exists with different event data.',
            ]);

        $this->assertDatabaseCount('score_events', 1);
        $this->assertDatabaseCount('idempotency_keys', 1);
        $this->assertDatabaseHas('leaderboard_entries', [
            'leaderboard_id' => $this->leaderboard->getKey(),
            'participant_id' => 'player_001',
            'score' => 50,
        ]);
    }

    public function test_it_returns_a_ranked_snapshot_for_the_authenticated_client(): void
    {
        $this->record('event_alice_1', 'player_alice', 100, 'request_alice_1');
        $this->record('event_bob_1', 'player_bob', 250, 'request_bob_1');
        $this->record('event_alice_2', 'player_alice', 200, 'request_alice_2');

        $this->getJson($this->leaderboardUrl('?limit=2'), $this->headers())
            ->assertOk()
            ->assertJsonPath('leaderboard.id', 'daily-demo')
            ->assertJsonCount(2, 'entries')
            ->assertJsonPath('entries.0.rank', 1)
            ->assertJsonPath('entries.0.participant_id', 'player_alice')
            ->assertJsonPath('entries.0.score', 300)
            ->assertJsonPath('entries.0.event_count', 2)
            ->assertJsonPath('entries.1.rank', 2)
            ->assertJsonPath('entries.1.participant_id', 'player_bob')
            ->assertJsonPath('entries.1.score', 250);
    }

    public function test_a_committed_event_invalidates_an_existing_ranking_snapshot(): void
    {
        $this->record('event_cache_1', 'player_cache', 100, 'request_cache_1');

        $this->getJson($this->leaderboardUrl(), $this->headers())
            ->assertOk()
            ->assertJsonPath('entries.0.score', 100);

        $this->record('event_cache_2', 'player_cache', 50, 'request_cache_2');

        $this->getJson($this->leaderboardUrl(), $this->headers())
            ->assertOk()
            ->assertJsonPath('entries.0.score', 150)
            ->assertJsonPath('entries.0.event_count', 2);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'event_id' => 'event_001',
            'participant_id' => 'player_001',
            'points' => 100,
            'occurred_at' => '2026-11-11T18:00:00Z',
            'metadata' => ['source' => 'test'],
        ], $overrides);
    }

    private function record(string $eventId, string $participantId, int $points, string $idempotencyKey): void
    {
        $this->postJson(
            $this->eventsUrl(),
            $this->payload([
                'event_id' => $eventId,
                'participant_id' => $participantId,
                'points' => $points,
            ]),
            $this->headers($idempotencyKey),
        )->assertCreated();
    }

    /** @return array<string, string> */
    private function headers(?string $idempotencyKey = null): array
    {
        $headers = ['X-API-Key' => $this->apiKey];

        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    private function eventsUrl(): string
    {
        return $this->leaderboardUrl('/events');
    }

    private function leaderboardUrl(string $suffix = ''): string
    {
        return '/api/v1/leaderboards/daily-demo'.$suffix;
    }
}
