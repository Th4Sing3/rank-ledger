<?php

namespace App\Data;

use App\Http\Requests\StoreScoreEventRequest;
use Carbon\CarbonImmutable;

final readonly class ScoreEventData
{
    /**
     * @param  array<string, bool|int|string>  $metadata
     */
    public function __construct(
        public string $eventId,
        public string $participantId,
        public int $points,
        public CarbonImmutable $occurredAt,
        public array $metadata,
    ) {}

    public static function fromRequest(StoreScoreEventRequest $request): self
    {
        $data = $request->validated();

        return new self(
            eventId: $data['event_id'],
            participantId: $data['participant_id'],
            points: $data['points'],
            occurredAt: CarbonImmutable::parse($data['occurred_at'])->utc(),
            metadata: $data['metadata'] ?? [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toFingerprintPayload(): array
    {
        return [
            'event_id' => $this->eventId,
            'participant_id' => $this->participantId,
            'points' => $this->points,
            'occurred_at' => $this->occurredAt->toAtomString(),
            'metadata' => $this->metadata,
        ];
    }
}
