<?php

namespace App\Jobs\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\WooCommerceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncWooCommerceStoreJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly string $storeId,
        public readonly ?array $resources = null,
    ) {
        $this->onQueue((string) config('lammah.woocommerce.queue', 'default'));
    }

    public function handle(WooCommerceSyncService $syncService): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $syncService->syncStore($store, $this->resources);
    }
}
