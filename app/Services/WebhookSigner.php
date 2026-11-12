<?php

namespace App\Services;

final class WebhookSigner
{
    public function makeHeader(string $secret, int $timestamp, string $body): string
    {
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, $secret);

        return "t={$timestamp},v1={$signature}";
    }
}
