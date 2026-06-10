<?php

namespace App\Jobs\Analytics;

use App\Models\WooCommerceStore;
use App\Services\Analytics\FraudRadarService;
use App\Services\Analytics\RfmChurnAnalyticsService;
use App\Services\Analytics\SalesForecastingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunStoreAnalyticsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public array $backoff = [300, 900];

    public function __construct(public readonly string $storeId)
    {
        $this->onQueue('analytics');
    }

    public function handle(
        SalesForecastingService $forecastingService,
        FraudRadarService $fraudRadarService,
        RfmChurnAnalyticsService $rfmChurnAnalyticsService,
    ): void {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $forecastingService->forecastNextMonth($store);
        $fraudRadarService->scanStore($store);
        $rfmChurnAnalyticsService->scoreStore($store);
    }
}
