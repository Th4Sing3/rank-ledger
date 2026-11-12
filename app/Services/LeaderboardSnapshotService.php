<?php

namespace App\Services;

use App\Models\ApiClient;
use App\Models\Leaderboard;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Cache;

final class LeaderboardSnapshotService
{
    /**
     * @return array{leaderboard: array<string, mixed>, entries: list<array<string, mixed>>}
     */
    public function get(ApiClient $client, string $externalId, int $limit): array
    {
        $leaderboard = Leaderboard::query()
            ->where('api_client_id', $client->getKey())
            ->where('external_id', $externalId)
            ->first();

        if ($leaderboard === null) {
            throw (new ModelNotFoundException)->setModel(Leaderboard::class, [$externalId]);
        }

        $version = Cache::get($this->versionKey($leaderboard->getKey()), 0);

        return Cache::remember(
            $this->snapshotKey($leaderboard->getKey(), (int) $version, $limit),
            now()->addSeconds(config('rank-ledger.cache_ttl_seconds')),
            function () use ($leaderboard, $limit): array {
                $entries = $leaderboard->entries()
                    ->orderByDesc('score')
                    ->orderBy('last_event_at')
                    ->orderBy('participant_id')
                    ->limit($limit)
                    ->get()
                    ->values()
                    ->map(fn ($entry, int $index): array => [
                        'rank' => $index + 1,
                        'participant_id' => $entry->participant_id,
                        'score' => $entry->score,
                        'event_count' => $entry->event_count,
                        'last_event_at' => $entry->last_event_at->toAtomString(),
                    ])
                    ->all();

                return [
                    'leaderboard' => [
                        'id' => $leaderboard->external_id,
                        'name' => $leaderboard->name,
                    ],
                    'entries' => $entries,
                ];
            },
        );
    }

    public function invalidate(string $leaderboardId): void
    {
        $key = $this->versionKey($leaderboardId);

        Cache::add($key, 0);
        Cache::increment($key);
    }

    private function versionKey(string $leaderboardId): string
    {
        return "rank-ledger:leaderboard:{$leaderboardId}:version";
    }

    private function snapshotKey(string $leaderboardId, int $version, int $limit): string
    {
        return "rank-ledger:leaderboard:{$leaderboardId}:{$version}:top:{$limit}";
    }
}
