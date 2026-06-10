<?php

namespace App\Jobs\WooCommerce;

use App\Models\SyncRun;
use App\Models\WooCommerceStore;
use App\Repositories\WooCommerce\WooCommerceSyncRepository;
use App\Services\WooCommerce\WooCommerceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncWooCommerceResourceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly string $storeId,
        public readonly string $resource,
        public readonly int $startPage = 1,
        public readonly ?string $syncRunId = null,
    ) {
        $this->onQueue('woocommerce-sync');
    }

    public function handle(WooCommerceSyncService $syncService, WooCommerceSyncRepository $repository): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);
        $run = $this->syncRunId === null
            ? $repository->startSyncRun($store, $this->resource, ['start_page' => $this->startPage])
            : SyncRun::query()->findOrFail($this->syncRunId);

        try {
            $synced = $syncService->syncResource($store, $this->resource, $this->startPage);
            $repository->finishSyncRun($run, ['synced' => $synced]);
        } catch (Throwable $exception) {
            $repository->failSyncRun($run, $exception->getMessage());

            throw $exception;
        }
    }
}
