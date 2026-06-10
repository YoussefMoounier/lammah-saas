<?php

namespace App\Jobs\Analytics;

use App\Models\WooCommerceStore;
use App\Services\Analytics\SalesForecastingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateSalesForecastJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $storeId,
        public readonly int $trainingMonths = 6,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(SalesForecastingService $forecastingService): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $forecastingService->forecastNextMonth($store, $this->trainingMonths);
    }
}
