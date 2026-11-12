<?php

namespace App\Actions;

use App\Data\ScoreEventData;
use App\Data\ScoreEventSubmission;
use App\Exceptions\ConflictingScoreEvent;
use App\Exceptions\IdempotencyConflict;
use App\Jobs\DispatchPendingOutboxMessages;
use App\Models\ApiClient;
use App\Models\IdempotencyKey;
use App\Models\Leaderboard;
use App\Models\LeaderboardEntry;
use App\Models\OutboxMessage;
use App\Models\ScoreEvent;
use App\Services\LeaderboardSnapshotService;
use App\Services\RequestFingerprint;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

final class SubmitScoreEvent
{
    public function __construct(
        private readonly RequestFingerprint $fingerprints,
        private readonly LeaderboardSnapshotService $snapshots,
    ) {}

    public function handle(
        ApiClient $client,
        string $leaderboardExternalId,
        string $idempotencyKey,
        ScoreEventData $data,
    ): ScoreEventSubmission {
        $fingerprint = $this->fingerprints->make(
            'POST',
            '/api/v1/leaderboards/'.$leaderboardExternalId.'/events',
            $data->toFingerprintPayload(),
        );

        $submission = DB::transaction(function () use ($client, $leaderboardExternalId, $idempotencyKey, $data, $fingerprint): ScoreEventSubmission {
            $now = now();
            $wasInserted = DB::table('idempotency_keys')->insertOrIgnore([
                'api_client_id' => $client->getKey(),
                'idempotency_key' => $idempotencyKey,
                'request_fingerprint' => $fingerprint,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var IdempotencyKey $idempotency */
            $idempotency = IdempotencyKey::query()
                ->where('api_client_id', $client->getKey())
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->firstOrFail();

            if ($wasInserted === 0) {
                if (! hash_equals($idempotency->request_fingerprint, $fingerprint)) {
                    throw new IdempotencyConflict;
                }

                if ($idempotency->completed_at !== null && $idempotency->response_body !== null && $idempotency->status_code !== null) {
                    return new ScoreEventSubmission(
                        statusCode: $idempotency->status_code,
                        body: $idempotency->response_body,
                        replayed: true,
                        stateChanged: false,
                        leaderboardId: null,
                    );
                }

                // A committed incomplete record should be impossible: it is created and completed atomically.
                throw new \LogicException('The idempotency record is incomplete.');
            }

            /**
             * This parent-row lock is intentionally the coordination boundary for one leaderboard.
             * It keeps the projection correct without pretending this small reference service needs sharding.
             *
             * @var Leaderboard|null $leaderboard
             */
            $leaderboard = Leaderboard::query()
                ->where('api_client_id', $client->getKey())
                ->where('external_id', $leaderboardExternalId)
                ->lockForUpdate()
                ->first();

            if ($leaderboard === null) {
                throw (new ModelNotFoundException)->setModel(Leaderboard::class, [$leaderboardExternalId]);
            }

            $existingEvent = ScoreEvent::query()
                ->where('leaderboard_id', $leaderboard->getKey())
                ->where('external_event_id', $data->eventId)
                ->first();

            if ($existingEvent !== null) {
                if (! hash_equals($existingEvent->payload_fingerprint, $fingerprint)) {
                    throw new ConflictingScoreEvent;
                }

                $entry = LeaderboardEntry::query()
                    ->where('leaderboard_id', $leaderboard->getKey())
                    ->where('participant_id', $existingEvent->participant_id)
                    ->firstOrFail();

                return $this->complete(
                    $idempotency,
                    200,
                    $this->responseBody('not_modified', $existingEvent, $entry),
                    false,
                    $leaderboard->getKey(),
                );
            }

            $entry = LeaderboardEntry::query()
                ->where('leaderboard_id', $leaderboard->getKey())
                ->where('participant_id', $data->participantId)
                ->lockForUpdate()
                ->first();

            if ($entry === null) {
                $entry = new LeaderboardEntry([
                    'leaderboard_id' => $leaderboard->getKey(),
                    'participant_id' => $data->participantId,
                    'score' => 0,
                    'event_count' => 0,
                    'last_event_at' => $data->occurredAt,
                ]);
            }

            $entry->score += $data->points;
            $entry->event_count++;
            $entry->last_event_at = $entry->last_event_at->greaterThan($data->occurredAt)
                ? $entry->last_event_at
                : $data->occurredAt;
            $entry->save();

            $event = ScoreEvent::query()->create([
                'leaderboard_id' => $leaderboard->getKey(),
                'external_event_id' => $data->eventId,
                'participant_id' => $data->participantId,
                'points' => $data->points,
                'payload_fingerprint' => $fingerprint,
                'occurred_at' => $data->occurredAt,
                'metadata' => $data->metadata,
            ]);

            OutboxMessage::query()->create([
                'api_client_id' => $client->getKey(),
                'topic' => 'leaderboard.score_recorded',
                'payload' => [
                    'id' => $event->external_event_id,
                    'type' => 'leaderboard.score_recorded',
                    'occurred_at' => $event->occurred_at->toAtomString(),
                    'data' => [
                        'leaderboard_id' => $leaderboard->external_id,
                        'participant_id' => $event->participant_id,
                        'points' => $event->points,
                        'score' => $entry->score,
                    ],
                ],
            ]);

            return $this->complete(
                $idempotency,
                201,
                $this->responseBody('created', $event, $entry),
                true,
                $leaderboard->getKey(),
            );
        }, attempts: 3);

        if ($submission->stateChanged && $submission->leaderboardId !== null) {
            $this->snapshots->invalidate($submission->leaderboardId);
            DispatchPendingOutboxMessages::dispatch()->onQueue('outbox');
        }

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function complete(
        IdempotencyKey $idempotency,
        int $statusCode,
        array $body,
        bool $stateChanged,
        string $leaderboardId,
    ): ScoreEventSubmission {
        $idempotency->forceFill([
            'status_code' => $statusCode,
            'response_body' => $body,
            'completed_at' => now(),
        ])->save();

        return new ScoreEventSubmission(
            statusCode: $statusCode,
            body: $body,
            replayed: false,
            stateChanged: $stateChanged,
            leaderboardId: $leaderboardId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function responseBody(string $changeStatus, ScoreEvent $event, LeaderboardEntry $entry): array
    {
        return [
            'change_status' => $changeStatus,
            'event' => [
                'id' => $event->external_event_id,
                'participant_id' => $event->participant_id,
                'points' => $event->points,
                'occurred_at' => $event->occurred_at->toAtomString(),
            ],
            'entry' => [
                'participant_id' => $entry->participant_id,
                'score' => $entry->score,
                'event_count' => $entry->event_count,
                'last_event_at' => $entry->last_event_at->toAtomString(),
            ],
        ];
    }
}
