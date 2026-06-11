<?php

namespace App\Services\Connector;

use App\Jobs\Analytics\CalculateOrderNetProfitJob;
use App\Models\WooCommerceStore;
use App\Models\WooWebhookEvent;
use App\Repositories\WooCommerce\WooCommerceSyncRepository;
use Illuminate\Support\Arr;
use Throwable;

class ConnectorIngestionService
{
    public function __construct(private readonly WooCommerceSyncRepository $repository)
    {
    }

    public function handshake(WooCommerceStore $store, array $payload): void
    {
        $metadata = array_merge($store->metadata ?? [], [
            'connector_site_url' => Arr::get($payload, 'site_url'),
            'connector_wp_version' => Arr::get($payload, 'wordpress_version'),
            'connector_wc_version' => Arr::get($payload, 'woocommerce_version'),
            'connector_connected_at' => now()->toIso8601String(),
        ]);

        $store->forceFill([
            'connector_status' => 'connected',
            'connector_last_seen_at' => now(),
            'connector_version' => Arr::get($payload, 'plugin_version'),
            'connector_last_error' => null,
            'metadata' => $metadata,
        ])->save();
    }

    public function ingestBulk(WooCommerceStore $store, string $resource, array $items): array
    {
        $this->markSeen($store);

        $synced = 0;
        $errors = [];

        foreach ($items as $item) {
            try {
                $model = match ($resource) {
                    'products' => $this->repository->upsertProduct($store, $item),
                    'customers' => $this->repository->upsertCustomer($store, $item),
                    'orders' => $this->repository->upsertOrder($store, $item),
                    default => throw new \InvalidArgumentException("Unsupported connector resource [{$resource}]."),
                };

                if ($resource === 'orders') {
                    CalculateOrderNetProfitJob::dispatch($model->id);
                }

                $synced++;
            } catch (Throwable $exception) {
                $errors[] = [
                    'woo_id' => Arr::get($item, 'id'),
                    'message' => $exception->getMessage(),
                ];
            }
        }

        $this->updateSyncState($store, $resource, $synced, $errors);

        return [
            'received' => count($items),
            'synced' => $synced,
            'failed' => count($errors),
            'errors' => array_slice($errors, 0, 10),
        ];
    }

    public function ingestOrderEvent(WooCommerceStore $store, array $payload): array
    {
        $eventId = (string) (Arr::get($payload, 'event_id') ?: Arr::get($payload, 'order.id') ?: hash('sha256', json_encode($payload)));
        $order = Arr::get($payload, 'order', $payload);

        $existing = WooWebhookEvent::query()
            ->where('store_id', $store->id)
            ->where('event_id', $eventId)
            ->first();

        if ($existing?->status === 'processed') {
            $this->markSeen($store);

            return ['event_id' => $existing->id, 'processed' => false, 'duplicate' => true];
        }

        $event = $this->repository->recordWebhookEvent(
            store: $store,
            headers: [
                'x-lammah-connector-event-id' => [$eventId],
                'x-lammah-connector-topic' => [Arr::get($payload, 'topic', 'order.updated')],
            ],
            payload: $order,
            rawPayload: json_encode($payload),
            signatureValid: true
        );

        $model = $this->repository->upsertOrder($store, $order);
        CalculateOrderNetProfitJob::dispatch($model->id);
        $this->repository->markWebhookProcessed($event);

        $this->markSeen($store);

        return ['event_id' => $event->id, 'processed' => true];
    }

    private function markSeen(WooCommerceStore $store): void
    {
        $store->forceFill([
            'connector_status' => 'connected',
            'connector_last_seen_at' => now(),
            'connector_last_error' => null,
        ])->save();
    }

    private function updateSyncState(WooCommerceStore $store, string $resource, int $synced, array $errors): void
    {
        $store->refresh();
        $settings = $store->sync_settings ?? [];
        $totals = $settings['connector_totals'] ?? [];
        $totals[$resource] = ($totals[$resource] ?? 0) + $synced;

        $store->forceFill([
            'connector_status' => $errors === [] ? 'connected' : 'warning',
            'connector_last_error' => $errors === [] ? null : ($errors[0]['message'] ?? 'Connector sync has errors.'),
            'sync_settings' => array_merge($settings, [
                'sync_state' => $errors === [] ? 'succeeded' : 'partial',
                'sync_finished_at' => now()->toIso8601String(),
                'connector_totals' => $totals,
                'connector_last_resource' => $resource,
                'connector_error_count' => count($errors),
                'connector_errors' => array_slice($errors, 0, 10),
            ]),
        ])->save();
    }
}
