<?php

return [
    'cache_ttl_seconds' => (int) env('LEADERBOARD_CACHE_TTL_SECONDS', 15),
    'rate_limit_per_minute' => (int) env('API_RATE_LIMIT_PER_MINUTE', 120),
    'webhook_timeout_seconds' => (int) env('WEBHOOK_TIMEOUT_SECONDS', 5),
    'webhook_tolerance_seconds' => (int) env('WEBHOOK_SIGNATURE_TOLERANCE_SECONDS', 300),
];
