<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\FraudSignalStatusRequest;
use App\Http\Resources\Api\V1\FraudRiskSignalResource;
use App\Jobs\Analytics\ScanFraudRadarJob;
use App\Models\FraudRiskSignal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FraudSignalController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(Request $request, string $merchant, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');

        $validated = $request->validate([
            'status' => ['nullable', 'string'],
            'severity' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return FraudRiskSignalResource::collection(
            FraudRiskSignal::query()
                ->where('store_id', $storeModel->id)
                ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
                ->when($validated['severity'] ?? null, fn ($query, string $severity) => $query->where('severity', $severity))
                ->orderByDesc('risk_score')
                ->orderByDesc('last_seen_at')
                ->paginate((int) ($validated['per_page'] ?? 20))
        );
    }

    public function scan(Request $request, string $merchant, string $store): JsonResponse
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');
        $validated = $request->validate([
            'lookback_days' => ['nullable', 'integer', 'min:1', 'max:365'],
            'minimum_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        ScanFraudRadarJob::dispatch(
            $storeModel->id,
            (int) ($validated['lookback_days'] ?? 30),
            (float) ($validated['minimum_score'] ?? 35),
        );

        return response()->json(['accepted' => true, 'queued' => true], 202);
    }

    public function update(FraudSignalStatusRequest $request, string $merchant, string $signal): FraudRiskSignalResource
    {
        $this->authorizeMerchant($request, $merchant, 'analytics.view');
        $fraudSignal = FraudRiskSignal::query()->where('merchant_id', $merchant)->findOrFail($signal);
        $evidence = $fraudSignal->evidence ?? [];

        if ($request->validated('notes') !== null) {
            $evidence['review'] = [
                'notes' => $request->validated('notes'),
                'reviewed_at' => now()->toIso8601String(),
            ];
        }

        $fraudSignal->forceFill([
            'status' => $request->validated('status'),
            'reviewed_by' => $request->user()->getKey(),
            'reviewed_at' => now(),
            'evidence' => $evidence,
        ])->save();

        return new FraudRiskSignalResource($fraudSignal);
    }
}
