<?php

use App\Http\Controllers\Api\V1\ShowLeaderboardController;
use App\Http\Controllers\Api\V1\StoreScoreEventController;
use Illuminate\Support\Facades\Route;

Route::middleware(['api.client', 'throttle:api-client'])
    ->prefix('v1')
    ->group(function (): void {
        Route::get('leaderboards/{externalId}', ShowLeaderboardController::class);
        Route::post('leaderboards/{externalId}/events', StoreScoreEventController::class);
    });
