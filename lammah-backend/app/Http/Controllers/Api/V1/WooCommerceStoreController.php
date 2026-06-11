<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWooCommerceStoreRequest;
use App\Http\Requests\Api\V1\SyncWooCommerceStoreRequest;
use App\Http\Resources\Api\V1\WooCommerceStoreResource;
use App\Jobs\WooCommerce\SyncWooCommerceStoreJob;
use App\Models\WooCommerceStore;
use App\Services\WooCommerce\WooCommerceConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class WooCommerceStoreController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant): AnonymousResourceCollection
    {
        $this->authorizeMerchant($request, $merchant, 'stores.view');

        return WooCommerceStoreResource::collection(
            WooCommerceStore::query()
                ->where('merchant_id', $merchant)
                ->orderByDesc('created_at')
                ->paginate((int) $request->integer('per_page', 20))
        );
    }

    public function store(
        StoreWooCommerceStoreRequest $request,
        string $merchant,
        WooCommerceConnectionService $connectionService,
    ): JsonResponse {
        $merchantModel = $this->authorizeMerchant($request, $merchant, 'stores.manage');
        $validated = $request->validated();
        $baseUrl = $this->normalizeBaseUrl($validated['base_url']);
        $baseUrlHash = $this->hashBaseUrl($baseUrl);

        $store = WooCommerceStore::withTrashed()
            ->where('merchant_id', $merchantModel->id)
            ->where('base_url_hash', $baseUrlHash)
            ->first();

        $payload = [
            'merchant_id' => $merchantModel->id,
            'name' => $validated['name'],
            'base_url' => $baseUrl,
            'base_url_hash' => $baseUrlHash,
            'consumer_key_encrypted' => $validated['consumer_key'],
            'consumer_secret_encrypted' => $validated['consumer_secret'],
            'webhook_secret_encrypted' => Str::random(40),
            'api_version' => 'wc/v3',
            'currency' => strtoupper($validated['currency'] ?? $merchantModel->default_currency ?? 'SAR'),
            'timezone' => $validated['timezone'] ?? $merchantModel->timezone ?? 'Asia/Riyadh',
            'status' => 'active',
            'sync_settings' => [],
            'metadata' => [],
        ];

        if ($store) {
            $store->restore();
            $store->forceFill($payload)->save();
            $statusCode = 200;
        } else {
            $store = WooCommerceStore::query()->create(['id' => (string) Str::ulid(), ...$payload]);
            $statusCode = 201;
        }

        $connection = null;
        if ((bool) ($validated['test_connection'] ?? true)) {
            $connection = $connectionService->testConnection($store);
            $store->refresh();
        }

        if ((bool) ($validated['sync_now'] ?? false)) {
            $this->markSyncQueued($store, ['categories', 'products', 'orders']);
            SyncWooCommerceStoreJob::dispatch($store->id, ['categories', 'products', 'orders']);
        }

        return response()->json([
            'data' => new WooCommerceStoreResource($store),
            'connection' => $connection,
            'sync_queued' => (bool) ($validated['sync_now'] ?? false),
        ], $statusCode);
    }

    public function test(
        Request $request,
        string $merchant,
        string $store,
        WooCommerceConnectionService $connectionService,
    ): JsonResponse {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'stores.manage');

        return response()->json([
            'data' => new WooCommerceStoreResource($storeModel),
            'connection' => $connectionService->testConnection($storeModel),
        ]);
    }

    public function sync(SyncWooCommerceStoreRequest $request, string $merchant, string $store): JsonResponse
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'stores.manage');
        $validated = $request->validated();
        $resources = $validated['resources'] ?? ['categories', 'products', 'orders'];

        if ((bool) ($validated['queued'] ?? true)) {
            $this->markSyncQueued($storeModel, $resources);
            SyncWooCommerceStoreJob::dispatch($storeModel->id, $resources);

            return response()->json([
                'accepted' => true,
                'queued' => true,
                'data' => new WooCommerceStoreResource($storeModel->refresh()),
            ], 202);
        }

        SyncWooCommerceStoreJob::dispatchSync($storeModel->id, $resources);

        return response()->json([
            'accepted' => true,
            'queued' => false,
            'data' => new WooCommerceStoreResource($storeModel->refresh()),
        ]);
    }

    private function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim(trim($baseUrl), '/');
    }

    private function hashBaseUrl(string $baseUrl): string
    {
        return hash('sha256', strtolower($baseUrl));
    }

    private function markSyncQueued(WooCommerceStore $store, array $resources): void
    {
        $store->forceFill([
            'last_error' => null,
            'sync_settings' => array_merge($store->sync_settings ?? [], [
                'sync_state' => 'queued',
                'sync_requested_at' => now()->toIso8601String(),
                'sync_resources' => array_values($resources),
            ]),
        ])->save();
    }
}
