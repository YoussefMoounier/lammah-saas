<?php

namespace App\Jobs\Operations;

use App\Models\PriceUpdateBatch;
use App\Services\Operations\BulkPriceUpdateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessPriceUpdateBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly string $batchId)
    {
        $this->onQueue('woocommerce-sync');
    }

    public function handle(BulkPriceUpdateService $bulkPriceUpdateService): void
    {
        $batch = PriceUpdateBatch::query()->findOrFail($this->batchId);

        if (! in_array($batch->status, ['queued', 'running'], true)) {
            return;
        }

        $bulkPriceUpdateService->processBatch($batch);
    }
}
