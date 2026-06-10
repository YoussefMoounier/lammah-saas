<?php

namespace App\Services\WooCommerce\Contracts;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\DTO\WooCommercePage;

interface WooCommerceClient
{
    public function get(WooCommerceStore $store, string $endpoint, array $query = []): array;

    public function post(WooCommerceStore $store, string $endpoint, array $payload = []): array;

    public function put(WooCommerceStore $store, string $endpoint, array $payload = []): array;

    public function page(WooCommerceStore $store, string $endpoint, int $page = 1, array $query = []): WooCommercePage;
}
