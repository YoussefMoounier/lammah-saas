<?php

namespace App\Services\WooCommerce;

class WooCommerceWebhookVerifier
{
    public function isValid(?string $secret, string $rawPayload, ?string $signature): bool
    {
        if ($secret === null || $secret === '' || $signature === null || $signature === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $rawPayload, $secret, true));

        return hash_equals($expected, $signature);
    }
}
