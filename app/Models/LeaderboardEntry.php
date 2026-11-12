<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LeaderboardEntry extends Model
{
    protected $fillable = [
        'leaderboard_id',
        'participant_id',
        'score',
        'event_count',
        'last_event_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'event_count' => 'integer',
            'last_event_at' => 'immutable_datetime',
        ];
    }

    public function leaderboard(): BelongsTo
    {
        return $this->belongsTo(Leaderboard::class);
    }
}
