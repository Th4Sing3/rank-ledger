<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ScoreEvent extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'leaderboard_id',
        'external_event_id',
        'participant_id',
        'points',
        'payload_fingerprint',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'occurred_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }

    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }
}
