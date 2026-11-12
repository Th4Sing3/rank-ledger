<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class IdempotencyKey extends Model
{
    protected $fillable = [
        'api_client_id',
        'idempotency_key',
        'request_fingerprint',
        'status_code',
        'response_body',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'response_body' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }
}
