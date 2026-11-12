<?php

namespace App\Jobs;

use App\Models\OutboxMessage;
use App\Models\WebhookDelivery;
use App\Services\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class DeliverLeaderboardWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public function __construct(public readonly string $outboxMessageId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [5, 30, 120, 300];
    }

    public function handle(WebhookSigner $signer): void
    {
        /** @var OutboxMessage $message */
        $message = OutboxMessage::query()
            ->with('apiClient')
            ->findOrFail($this->outboxMessageId);
        $client = $message->apiClient;

        if ($client->webhook_url === null || $client->webhook_secret === null) {
            $this->record($message, 'skipped');

            return;
        }

        $body = json_encode($message->payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();

        try {
            $response = Http::timeout(config('rank-ledger.webhook_timeout_seconds'))
                ->acceptJson()
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Rank-Ledger-Event' => $message->payload['id'],
                    'X-Rank-Ledger-Delivery' => $message->getKey(),
                    'X-Rank-Ledger-Signature' => $signer->makeHeader($client->webhook_secret, $timestamp, $body),
                ])
                ->withBody($body, 'application/json')
                ->post($client->webhook_url);
        } catch (ConnectionException $exception) {
            $this->record($message, 'retrying', error: $exception->getMessage());

            throw $exception;
        }

        if ($response->successful()) {
            $this->record($message, 'delivered', responseStatus: $response->status());

            return;
        }

        $error = 'Receiver responded with HTTP '.$response->status().'.';

        if ($response->status() === 429 || $response->serverError()) {
            $this->record($message, 'retrying', $response->status(), $error);

            throw new RuntimeException($error);
        }

        $this->record($message, 'failed', $response->status(), $error);
        $this->fail(new RuntimeException($error));
    }

    public function failed(Throwable $exception): void
    {
        // The per-attempt record above is the audit trail; never write webhook payloads to logs.
    }

    private function record(
        OutboxMessage $message,
        string $status,
        ?int $responseStatus = null,
        ?string $error = null,
    ): void {
        WebhookDelivery::query()->updateOrCreate(
            [
                'outbox_message_id' => $message->getKey(),
                'attempt' => $this->attempts(),
            ],
            [
                'api_client_id' => $message->api_client_id,
                'status' => $status,
                'response_status' => $responseStatus,
                'error_message' => $error === null ? null : Str::limit($error, 1_000, ''),
                'attempted_at' => now(),
            ],
        );
    }
}
