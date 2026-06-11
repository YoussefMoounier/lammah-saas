<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\WooCommerceStoreResource;
use App\Services\Connector\ConnectorAuthenticator;
use App\Services\Connector\ConnectorIngestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectorIngestionController extends Controller
{
    public function __construct(
        private readonly ConnectorAuthenticator $authenticator,
        private readonly ConnectorIngestionService $ingestion,
    ) {
    }

    public function handshake(Request $request, string $store): JsonResponse
    {
        $storeModel = $this->authenticator->authenticate($request, $store);
        $payload = $request->validate([
            'site_url' => ['required', 'url', 'max:2048'],
            'plugin_version' => ['required', 'string', 'max:32'],
            'wordpress_version' => ['nullable', 'string', 'max:32'],
            'woocommerce_version' => ['nullable', 'string', 'max:32'],
        ]);

        $this->ingestion->handshake($storeModel, $payload);

        return response()->json([
            'ok' => true,
            'data' => new WooCommerceStoreResource($storeModel->refresh()),
        ]);
    }

    public function products(Request $request, string $store): JsonResponse
    {
        return $this->bulk($request, $store, 'products');
    }

    public function customers(Request $request, string $store): JsonResponse
    {
        return $this->bulk($request, $store, 'customers');
    }

    public function orders(Request $request, string $store): JsonResponse
    {
        return $this->bulk($request, $store, 'orders');
    }

    public function orderEvent(Request $request, string $store): JsonResponse
    {
        $storeModel = $this->authenticator->authenticate($request, $store);
        $payload = $request->validate([
            'event_id' => ['nullable', 'string', 'max:191'],
            'topic' => ['nullable', 'string', 'max:80'],
            'order' => ['required', 'array'],
        ]);

        $result = $this->ingestion->ingestOrderEvent($storeModel, $payload);

        return response()->json(['ok' => true, ...$result], 202);
    }

    private function bulk(Request $request, string $store, string $resource): JsonResponse
    {
        $storeModel = $this->authenticator->authenticate($request, $store);
        $payload = $request->validate([
            'items' => ['required', 'array', 'max:100'],
            'items.*' => ['required', 'array'],
        ]);

        $result = $this->ingestion->ingestBulk($storeModel, $resource, $payload['items']);

        return response()->json([
            'ok' => true,
            'resource' => $resource,
            ...$result,
        ], $result['failed'] > 0 ? 207 : 202);
    }
}
