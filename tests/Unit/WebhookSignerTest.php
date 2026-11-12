<?php

namespace Tests\Unit;

use App\Services\WebhookSigner;
use Tests\TestCase;

final class WebhookSignerTest extends TestCase
{
    public function test_it_creates_a_versioned_hmac_header_for_the_exact_raw_body(): void
    {
        $secret = 'test-secret';
        $timestamp = 1_731_537_600;
        $body = '{"event_id":"event_001","score":125}';
        $expected = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        $header = (new WebhookSigner)->makeHeader($secret, $timestamp, $body);

        $this->assertSame("t={$timestamp},v1={$expected}", $header);
    }
}
