<?php

namespace Tests\Unit\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\WooCommerceRestClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WooCommerceRestClientTest extends TestCase
{
    public function test_it_requests_paginated_woocommerce_resources_with_basic_auth(): void
    {
        Http::fake([
            'https://store.example.test/wp-json/wc/v3/products*' => Http::response(
                [['id' => 10, 'name' => '12 Month IPTV']],
                200,
                ['X-WP-Total' => '1', 'X-WP-TotalPages' => '1']
            ),
        ]);

        $store = new WooCommerceStore([
            'base_url' => 'https://store.example.test',
            'api_version' => 'wc/v3',
            'consumer_key_encrypted' => 'ck_test',
            'consumer_secret_encrypted' => 'cs_test',
        ]);

        $page = (new WooCommerceRestClient())->page($store, 'products', 1);

        $this->assertSame(1, $page->total);
        $this->assertSame(1, $page->totalPages);
        $this->assertSame('12 Month IPTV', $page->items[0]['name']);

        Http::assertSent(fn ($request): bool => (
            str_contains($request->url(), '/wp-json/wc/v3/products')
            && str_contains($request->url(), 'page=1')
            && str_contains($request->url(), 'per_page=')
            && str_starts_with($request->header('Authorization')[0] ?? '', 'Basic ')
        ));
    }
}
