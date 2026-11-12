<?php

namespace App\Providers;

use App\Models\ApiClient;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        RateLimiter::for('api-client', function (Request $request): Limit {
            $client = $request->attributes->get('apiClient');
            $identifier = $client instanceof ApiClient ? $client->getKey() : $request->ip();

            return Limit::perMinute(config('rank-ledger.rate_limit_per_minute'))
                ->by($identifier);
        });
    }
}
