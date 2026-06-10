<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\WooCommerce\ProcessWooCommerceWebhookJob;
use App\Models\WooCommerceStore;
use App\Services\WooCommerce\WooCommerceWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WooCommerceWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        string $store,
        WooCommerceWebhookService $webhookService,
    ): JsonResponse {
        $woocommerceStore = WooCommerceStore::query()->where('status', 'active')->findOrFail($store);
        $event = $webhookService->persistIncomingRequest($woocommerceStore, $request);

        if ($event->signature_valid) {
            ProcessWooCommerceWebhookJob::dispatch($event->id);
        }

        return response()->json([
            'accepted' => true,
            'event_id' => $event->id,
        ], 202);
    }
}
