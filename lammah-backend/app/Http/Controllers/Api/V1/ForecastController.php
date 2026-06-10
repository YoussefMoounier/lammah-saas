<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\GenerateForecastRequest;
use App\Http\Resources\Api\V1\SalesForecastResource;
use App\Jobs\Analytics\GenerateSalesForecastJob;
use App\Models\SalesForecast;
use App\Services\Analytics\SalesForecastingService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ForecastController extends Controller
{
    use AuthorizesMerchantAccess;

    public function index(GenerateForecastRequest $request, string $merchant, string $store): AnonymousResourceCollection
    {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');

        return SalesForecastResource::collection(
            SalesForecast::query()
                ->where('store_id', $storeModel->id)
                ->orderByDesc('forecast_month')
                ->paginate((int) $request->query('per_page', 15))
        );
    }

    public function generate(
        GenerateForecastRequest $request,
        string $merchant,
        string $store,
        SalesForecastingService $forecastingService,
    ): SalesForecastResource|\Illuminate\Http\JsonResponse {
        $storeModel = $this->authorizeStore($request, $merchant, $store, 'analytics.view');
        $trainingMonths = (int) ($request->validated('training_months') ?? 6);

        if ((bool) $request->validated('queued', false)) {
            GenerateSalesForecastJob::dispatch($storeModel->id, $trainingMonths);

            return response()->json(['accepted' => true, 'queued' => true], 202);
        }

        return new SalesForecastResource($forecastingService->forecastNextMonth($storeModel, $trainingMonths));
    }
}
