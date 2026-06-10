<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerceStore;
use App\Models\WooWebhookEvent;
use App\Repositories\WooCommerce\WooCommerceSyncRepository;
use Illuminate\Http\Request;

class WooCommerceWebhookService
{
    public function __construct(
        private readonly WooCommerceWebhookVerifier $verifier,
        private readonly WooCommerceSyncRepository $repository,
    ) {
    }

    public function persistIncomingRequest(WooCommerceStore $store, Request $request): WooWebhookEvent
    {
        $rawPayload = $request->getContent();
        $headers = $this->normalizeHeaders($request->headers->all());
        $payload = json_decode($rawPayload, true) ?: [];
        $signature = $headers['x-wc-webhook-signature'][0] ?? null;
        $isValid = $this->verifier->isValid($store->webhookSecret(), $rawPayload, $signature);

        return $this->repository->recordWebhookEvent(
            store: $store,
            headers: $headers,
            payload: $payload,
            rawPayload: $rawPayload,
            signatureValid: $isValid
        );
    }

    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];

        foreach ($headers as $name => $value) {
            $normalized[strtolower($name)] = array_values((array) $value);
        }

        return $normalized;
    }
}
