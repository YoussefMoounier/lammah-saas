<?php

namespace App\Jobs\Analytics;

use App\Services\Analytics\NetProfitAnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CalculateOrderNetProfitJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public function __construct(public readonly string $orderId)
    {
        $this->onQueue('analytics');
    }

    public function handle(NetProfitAnalyticsService $profitAnalyticsService): void
    {
        $profitAnalyticsService->calculateForOrder($this->orderId);
    }
}
