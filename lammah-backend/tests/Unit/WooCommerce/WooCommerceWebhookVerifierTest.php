<?php

namespace Tests\Unit\WooCommerce;

use App\Services\WooCommerce\WooCommerceWebhookVerifier;
use Tests\TestCase;

class WooCommerceWebhookVerifierTest extends TestCase
{
    public function test_it_accepts_valid_woocommerce_hmac_signature(): void
    {
        $payload = json_encode(['id' => 123, 'status' => 'completed'], JSON_THROW_ON_ERROR);
        $secret = 'super-secret-webhook-token';
        $signature = base64_encode(hash_hmac('sha256', $payload, $secret, true));

        $this->assertTrue(
            (new WooCommerceWebhookVerifier())->isValid($secret, $payload, $signature)
        );
    }

    public function test_it_rejects_invalid_woocommerce_hmac_signature(): void
    {
        $this->assertFalse(
            (new WooCommerceWebhookVerifier())->isValid('secret', '{"id":123}', 'not-the-real-signature')
        );
    }
}
