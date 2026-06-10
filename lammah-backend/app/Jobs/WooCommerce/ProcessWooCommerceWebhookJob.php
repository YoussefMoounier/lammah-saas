<?php

namespace App\Jobs\WooCommerce;

use App\Models\WooWebhookEvent;
use App\Services\WooCommerce\WooCommerceSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWooCommerceWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public array $backoff = [30, 120, 300, 900, 1800];

    public function __construct(public readonly string $webhookEventId)
    {
        $this->onQueue('woocommerce-webhooks');
    }

    public function handle(WooCommerceSyncService $syncService): void
    {
        $event = WooWebhookEvent::query()->findOrFail($this->webhookEventId);

        if (in_array($event->status, ['processed', 'ignored'], true)) {
            return;
        }

        $syncService->processWebhookEvent($event);
    }
}
