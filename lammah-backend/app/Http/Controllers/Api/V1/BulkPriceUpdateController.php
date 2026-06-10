<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BulkPriceUpdateRequest;
use App\Http\Resources\Api\V1\PriceUpdateBatchResource;
use App\Models\PriceUpdateBatch;
use App\Services\Operations\BulkPriceUpdateService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BulkPriceUpdateController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'pricing.manage');
        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return PriceUpdateBatchResource::collection(
            PriceUpdateBatch::query()
                ->withCount('items')
                ->where('store_id', $storeModel->id)
                ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->orderByDesc('created_at')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function store(
        BulkPriceUpdateRequest $request,
        string $merchant,
        string $store,
        BulkPriceUpdateService $bulkPriceUpdateService,
    ): PriceUpdateBatchResource {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'pricing.manage');
        $batch = $bulkPriceUpdateService->createBatch($storeModel, $request->user()->getKey(), $request->validated());

        return new PriceUpdateBatchResource($batch->loadCount('items'));
    }

    public function show(Request $request, string $merchant, string $batch): PriceUpdateBatchResource
    {
        $this->authorizeMerchant($request, $merchant, 'pricing.manage');

        return new PriceUpdateBatchResource(
            PriceUpdateBatch::query()
                ->with(['items' => fn ($query) => $query->orderBy('created_at')])
                ->withCount('items')
                ->where('merchant_id', $merchant)
                ->findOrFail($batch)
        );
    }
}
