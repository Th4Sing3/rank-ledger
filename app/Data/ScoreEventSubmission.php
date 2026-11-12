<?php

namespace App\Data;

final readonly class ScoreEventSubmission
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        public int $statusCode,
        public array $body,
        public bool $replayed,
        public bool $stateChanged,
        public ?string $leaderboardId,
    ) {}
}
