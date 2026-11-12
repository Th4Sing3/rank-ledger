<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ShowLeaderboardRequest;
use App\Models\ApiClient;
use App\Services\LeaderboardSnapshotService;
use Illuminate\Http\JsonResponse;

final class ShowLeaderboardController extends Controller
{
    public function __invoke(
        ShowLeaderboardRequest $request,
        LeaderboardSnapshotService $snapshots,
        string $externalId,
    ): JsonResponse {
        $client = $request->attributes->get('apiClient');
        assert($client instanceof ApiClient);

        return response()->json($snapshots->get(
            client: $client,
            externalId: $externalId,
            limit: (int) $request->validated('limit', 10),
        ));
    }
}
