<?php

namespace App\Jobs\WooCommerce;

use App\Models\WooCommerceStore;
use App\Services\WooCommerce\WooCommerceWebhookRegistrar;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegisterWooCommerceWebhooksJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 180, 600];

    public function __construct(
        public readonly string $storeId,
        public readonly ?string $deliveryUrl = null,
    ) {
        $this->onQueue('woocommerce-sync');
    }

    public function handle(WooCommerceWebhookRegistrar $registrar): void
    {
        $store = WooCommerceStore::query()->where('status', 'active')->findOrFail($this->storeId);

        $registrar->ensureDefaultWebhooks($store, $this->deliveryUrl);
    }
}
