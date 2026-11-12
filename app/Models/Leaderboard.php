<?php

namespace App\Models;

use Database\Factories\LeaderboardFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Leaderboard extends Model
{
    /** @use HasFactory<LeaderboardFactory> */
    use HasFactory;

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'api_client_id',
        'external_id',
        'name',
    ];

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LeaderboardEntry::class);
    }

    public function scoreEvents(): HasMany
    {
        return $this->hasMany(ScoreEvent::class);
    }
}
