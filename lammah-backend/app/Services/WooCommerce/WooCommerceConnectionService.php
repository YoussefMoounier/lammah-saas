<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\Contracts\WooCommerceClient;
use Throwable;

class WooCommerceConnectionService
{
    public function __construct(private readonly WooCommerceClient $client)
    {
    }

    public function testConnection(WooCommerceStore $store): array
    {
        try {
            $page = $this->client->page($store, 'products', 1, ['status' => 'any']);

            $store->forceFill([
                'status' => 'active',
                'last_error' => null,
            ])->save();

            return [
                'ok' => true,
                'reachable' => true,
                'authenticated' => true,
                'sample_count' => count($page->items),
            ];
        } catch (Throwable $exception) {
            $store->forceFill([
                'status' => 'error',
                'last_failed_sync_at' => now(),
                'last_error' => $exception->getMessage(),
            ])->save();

            return [
                'ok' => false,
                'reachable' => false,
                'authenticated' => false,
                'error' => $exception->getMessage(),
            ];
        }
    }
}
