<?php

namespace App\Services\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\Contracts\WooCommerceClient;
use App\Services\WooCommerce\DTO\WooCommercePage;
use App\Services\WooCommerce\Exceptions\WooCommerceApiException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class WooCommerceRestClient implements WooCommerceClient
{
    public function get(WooCommerceStore $store, string $endpoint, array $query = []): array
    {
        return $this->send($store, 'get', $endpoint, $query)->json() ?? [];
    }

    public function post(WooCommerceStore $store, string $endpoint, array $payload = []): array
    {
        return $this->send($store, 'post', $endpoint, $payload)->json() ?? [];
    }

    public function put(WooCommerceStore $store, string $endpoint, array $payload = []): array
    {
        return $this->send($store, 'put', $endpoint, $payload)->json() ?? [];
    }

    public function page(WooCommerceStore $store, string $endpoint, int $page = 1, array $query = []): WooCommercePage
    {
        $perPage = (int) config('lammah.woocommerce.page_size', 100);

        $response = $this->send($store, 'get', $endpoint, array_merge($query, [
            'page' => $page,
            'per_page' => $perPage,
        ]));

        return new WooCommercePage(
            items: $response->json() ?? [],
            page: $page,
            perPage: $perPage,
            total: $this->nullableInt($response->header('X-WP-Total')),
            totalPages: $this->nullableInt($response->header('X-WP-TotalPages')),
        );
    }

    private function send(WooCommerceStore $store, string $method, string $endpoint, array $payloadOrQuery): Response
    {
        $response = Http::baseUrl(rtrim($store->base_url, '/'))
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('lammah.woocommerce.timeout_seconds', 20))
            ->retry(
                (int) config('lammah.woocommerce.retry_attempts', 3),
                (int) config('lammah.woocommerce.retry_sleep_ms', 300),
                null,
                false
            )
            ->withBasicAuth($store->consumerKey(), $store->consumerSecret())
           ->{$method}($this->apiPath($store, $endpoint), $payloadOrQuery);

        if ($response->failed()) {
            throw new WooCommerceApiException(
                endpoint: $endpoint,
                statusCode: $response->status(),
                responseBody: $response->json() ?? $response->body(),
                message: "WooCommerce {$method} {$endpoint} failed with HTTP {$response->status()}."
            );
        }

        return $response;
    }

    private function apiPath(WooCommerceStore $store, string $endpoint): string
    {
        return sprintf('/wp-json/%s/%s', trim($store->api_version, '/'), ltrim($endpoint, '/'));
    }

    private function nullableInt(?string $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
