<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RfmCustomerScoreResource;
use App\Jobs\Analytics\ScoreRfmChurnJob;
use App\Models\RfmCustomerScore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RfmScoreController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');
        $validated = $request->validate([
            'segment' => ['nullable', 'string'],
            'min_churn' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return RfmCustomerScoreResource::collection(
            RfmCustomerScore::query()
                ->where('store_id', $storeModel->id)
                ->when($validated['segment'] ?? null, fn ($query, string $segment) => $query->where('segment', $segment))
                ->when($validated['min_churn'] ?? null, fn ($query, mixed $min) => $query->where('churn_probability', '>=', $min))
                ->orderByDesc('calculated_at')
                ->orderByDesc('churn_probability')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function refresh(Request $request, string $merchant, string $store): JsonResponse
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');
        $validated = $request->validate([
            'lookback_days' => ['nullable', 'integer', 'min:30', 'max:730'],
        ]);

        ScoreRfmChurnJob::dispatch($storeModel->id, (int) ($validated['lookback_days'] ?? 180));

        return response()->json(['accepted' => true, 'queued' => true], 202);
    }
}
