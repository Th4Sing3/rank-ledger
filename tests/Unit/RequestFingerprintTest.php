<?php

namespace Tests\Unit;

use App\Services\RequestFingerprint;
use Tests\TestCase;

final class RequestFingerprintTest extends TestCase
{
    public function test_it_is_stable_when_associative_payload_keys_arrive_in_a_different_order(): void
    {
        $fingerprints = new RequestFingerprint;

        $first = $fingerprints->make('post', '/api/v1/leaderboards/daily-demo/events', [
            'event_id' => 'event_001',
            'metadata' => [
                'source' => 'checkout',
                'campaign' => 'launch',
            ],
            'points' => 125,
        ]);
        $second = $fingerprints->make('POST', '/api/v1/leaderboards/daily-demo/events', [
            'points' => 125,
            'metadata' => [
                'campaign' => 'launch',
                'source' => 'checkout',
            ],
            'event_id' => 'event_001',
        ]);

        $this->assertSame($first, $second);
    }

    public function test_it_changes_when_a_list_order_changes(): void
    {
        $fingerprints = new RequestFingerprint;

        $first = $fingerprints->make('POST', '/events', ['tags' => ['first', 'second']]);
        $second = $fingerprints->make('POST', '/events', ['tags' => ['second', 'first']]);

        $this->assertNotSame($first, $second);
    }
}
