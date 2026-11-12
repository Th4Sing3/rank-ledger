<?php

use App\Exceptions\ConflictingScoreEvent;
use App\Exceptions\IdempotencyConflict;
use App\Http\Middleware\AuthenticateApiClient;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.client' => AuthenticateApiClient::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (IdempotencyConflict $exception, Request $request) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        });

        $exceptions->render(function (ConflictingScoreEvent $exception, Request $request) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 409);
        });
    })->create();
