<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WebhookDelivery extends Model
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'outbox_message_id',
        'api_client_id',
        'attempt',
        'status',
        'response_status',
        'error_message',
        'attempted_at',
    ];

    protected function casts(): array
    {
        return [
            'attempt' => 'integer',
            'response_status' => 'integer',
            'attempted_at' => 'immutable_datetime',
        ];
    }

    public function apiClient(): BelongsTo
    {
        return $this->belongsTo(ApiClient::class);
    }

    public function outboxMessage(): BelongsTo
    {
        return $this->belongsTo(OutboxMessage::class);
    }
}
