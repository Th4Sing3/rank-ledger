<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitScoreEvent;
use App\Data\ScoreEventData;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreScoreEventRequest;
use App\Models\ApiClient;
use Illuminate\Http\JsonResponse;

final class StoreScoreEventController extends Controller
{
    public function __invoke(
        StoreScoreEventRequest $request,
        SubmitScoreEvent $submitScoreEvent,
        string $externalId,
    ): JsonResponse {
        $client = $request->attributes->get('apiClient');
        assert($client instanceof ApiClient);

        $submission = $submitScoreEvent->handle(
            client: $client,
            leaderboardExternalId: $externalId,
            idempotencyKey: $request->validated('idempotency_key'),
            data: ScoreEventData::fromRequest($request),
        );

        return response()
            ->json($submission->body, $submission->statusCode)
            ->header('Idempotent-Replay', $submission->replayed ? 'true' : 'false');
    }
}
