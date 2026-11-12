<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateApiClient
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-API-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return response()->json(['message' => 'Authentication required.'], 401);
        }

        $client = ApiClient::query()
            ->where('api_key_hash', hash('sha256', $apiKey))
            ->first();

        if ($client === null) {
            return response()->json(['message' => 'Invalid API key.'], 401);
        }

        $request->attributes->set('apiClient', $client);

        return $next($request);
    }
}
