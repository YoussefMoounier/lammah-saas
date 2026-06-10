<?php

namespace App\Jobs\Analytics;

use App\Models\WooCommerceStore;
use App\Services\Analytics\RfmChurnAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScoreRfmChurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $storeId,
        public readonly int $lookbackDays = 180,
    ) {
        $this->onQueue('analytics');
    }

    public function handle(RfmChurnAnalyticsService $rfmChurnAnalyticsService): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $rfmChurnAnalyticsService->scoreStore($store, $this->lookbackDays);
    }
}
