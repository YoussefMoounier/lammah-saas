<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderProfitSnapshotResource;
use App\Jobs\Analytics\CalculateOrderNetProfitJob;
use App\Models\OrderProfitSnapshot;
use App\Models\WooOrder;
use App\Services\Analytics\NetProfitAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NetProfitController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'revenue.view');
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return OrderProfitSnapshotResource::collection(
            OrderProfitSnapshot::query()
                ->where('store_id', $storeModel->id)
                ->when($validated['from'] ?? null, fn ($query, string $from) => $query->where('calculated_at', '>=', $from))
                ->when($validated['to'] ?? null, fn ($query, string $to) => $query->where('calculated_at', '<=', $to))
                ->orderByDesc('calculated_at')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function recalculate(
        Request $request,
        string $merchant,
        string $order,
        NetProfitAnalyticsService $profitAnalyticsService,
    ): OrderProfitSnapshotResource|JsonResponse {
        $this->authorizeMerchant($request, $merchant, 'revenue.view');
        $validated = $request->validate(['queued' => ['nullable', 'boolean']]);
        $wooOrder = WooOrder::query()
            ->whereHas('store', fn ($query) => $query->where('merchant_id', $merchant))
            ->findOrFail($order);

        if ((bool) ($validated['queued'] ?? false)) {
            CalculateOrderNetProfitJob::dispatch($wooOrder->id);

            return response()->json(['accepted' => true, 'queued' => true], 202);
        }

        return new OrderProfitSnapshotResource($profitAnalyticsService->calculateForOrder($wooOrder));
    }
}
