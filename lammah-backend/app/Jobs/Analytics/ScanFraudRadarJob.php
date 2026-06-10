<?php

namespace App\Jobs\Analytics;

use App\Models\WooCommerceStore;
use App\Services\Analytics\FraudRadarService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanFraudRadarJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $storeId,
        public readonly int $lookbackDays = 30,
        public readonly float $minimumScore = 35.0,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(FraudRadarService $fraudRadarService): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $fraudRadarService->scanStore($store, $this->lookbackDays, $this->minimumScore);
    }
}
