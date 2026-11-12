<?php

namespace App\Jobs;

use App\Models\OutboxMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class DispatchPendingOutboxMessages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(): void
    {
        OutboxMessage::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit(100)
            ->get()
            ->each(function (OutboxMessage $message): void {
                /*
                 * Dispatch before marking published. A crash can duplicate a delivery, but cannot
                 * silently lose it; consumers receive the stable event_id for their own dedupe.
                 */
                DeliverLeaderboardWebhook::dispatch($message->getKey())->onQueue('webhooks');

                $message->forceFill(['published_at' => now()])->save();
            });
    }
}
