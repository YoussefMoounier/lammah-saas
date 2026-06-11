<?php

namespace App\Services\WooCommerce;

use App\Jobs\Analytics\CalculateOrderNetProfitJob;
use App\Models\SyncRun;
use App\Models\WooCommerceStore;
use App\Models\WooOrder;
use App\Models\WooWebhookEvent;
use App\Repositories\WooCommerce\WooCommerceSyncRepository;
use App\Services\WooCommerce\Contracts\WooCommerceClient;
use Illuminate\Support\Arr;
use Throwable;

class WooCommerceSyncService
{
    private const RESOURCES = ['categories', 'products', 'customers', 'orders'];

    public function __construct(
        private readonly WooCommerceClient $client,
        private readonly WooCommerceSyncRepository $repository,
    ) {
    }

    public function syncStore(WooCommerceStore $store, ?array $resources = null): array
    {
        $resources = $resources ?: self::RESOURCES;
        $run = $this->repository->startSyncRun($store, 'full', ['resources' => $resources]);
        $totals = [];

        try {
            $this->updateStoreSyncSettings($store, [
                'sync_state' => 'running',
                'sync_started_at' => now()->toIso8601String(),
                'sync_resources' => array_values($resources),
            ]);

            foreach ($resources as $resource) {
                $totals[$resource] = $this->syncResource($store, $resource);
            }

            $this->repository->finishSyncRun($run, $totals);
            $store->forceFill([
                'last_successful_sync_at' => now(),
                'last_error' => null,
                'sync_settings' => array_merge($store->sync_settings ?? [], [
                    'sync_state' => 'succeeded',
                    'sync_finished_at' => now()->toIso8601String(),
                    'sync_totals' => $totals,
                ]),
            ])->save();

            return $totals;
        } catch (Throwable $exception) {
            $this->repository->failSyncRun($run, $exception->getMessage(), $totals);
            $store->forceFill([
                'last_failed_sync_at' => now(),
                'last_error' => $exception->getMessage(),
                'sync_settings' => array_merge($store->sync_settings ?? [], [
                    'sync_state' => 'failed',
                    'sync_finished_at' => now()->toIso8601String(),
                    'sync_totals' => $totals,
                ]),
            ])->save();

            throw $exception;
        }
    }

    public function syncResource(WooCommerceStore $store, string $resource, int $startPage = 1): int
    {
        $this->assertSupportedResource($resource);

        $synced = 0;
        $pageNumber = $startPage;
        $maxPages = (int) config('lammah.woocommerce.max_pages_per_job', 25);

        do {
            $page = $this->client->page(
                $store,
                $this->endpointFor($resource),
                $pageNumber,
                $this->queryFor($resource)
            );

            foreach ($page->items as $item) {
                $syncedModel = $this->upsertResource($store, $resource, $item);
                $this->dispatchPostSyncAnalytics($syncedModel);
                $synced++;
            }

            $pageNumber++;
        } while ($page->hasNextPage() && ($pageNumber - $startPage) < $maxPages);

        return $synced;
    }

    public function syncSingleResource(WooCommerceStore $store, string $resource, int|string $wooResourceId): void
    {
        $this->assertSupportedResource($resource);

        $payload = $this->client->get($store, sprintf('%s/%s', $this->endpointFor($resource), $wooResourceId));

        $syncedModel = $this->upsertResource($store, $resource, $payload);
        $this->dispatchPostSyncAnalytics($syncedModel);
    }

    public function processWebhookEvent(WooWebhookEvent $event): void
    {
        if (! $event->signature_valid) {
            $this->repository->markWebhookFailed($event, 'Rejected invalid WooCommerce webhook signature.');

            return;
        }

        $store = WooCommerceStore::query()->findOrFail($event->store_id);
        $resource = $this->resourceFromWebhook($event);

        $this->repository->markWebhookProcessing($event);

        try {
            if ($this->isDeletionTopic($event->topic)) {
                $this->markWebhookResourceDeleted($store, $resource, $event->payload);
            } else {
                $syncedModel = $this->upsertResource($store, $resource, $event->payload);
                $this->dispatchPostSyncAnalytics($syncedModel);
            }

            $this->repository->markWebhookProcessed($event);
        } catch (Throwable $exception) {
            $this->repository->markWebhookFailed($event, $exception->getMessage());

            throw $exception;
        }
    }

    private function upsertResource(WooCommerceStore $store, string $resource, array $payload): mixed
    {
        return match ($resource) {
            'customers' => $this->repository->upsertCustomer($store, $payload),
            'categories' => $this->repository->upsertCategory($store, $payload),
            'products' => $this->repository->upsertProduct($store, $payload),
            'orders' => $this->repository->upsertOrder($store, $payload),
            default => throw new \InvalidArgumentException("Unsupported WooCommerce resource [{$resource}]."),
        };
    }

    private function dispatchPostSyncAnalytics(mixed $syncedModel): void
    {
        if ($syncedModel instanceof WooOrder) {
            CalculateOrderNetProfitJob::dispatch($syncedModel->id);
        }
    }

    private function markWebhookResourceDeleted(WooCommerceStore $store, string $resource, array $payload): void
    {
        $wooResourceId = Arr::get($payload, 'id');

        if ($wooResourceId === null) {
            return;
        }

        match ($resource) {
            'customers' => $this->repository->markCustomerDeleted($store, $wooResourceId),
            'products' => $this->repository->markProductDeleted($store, $wooResourceId),
            'orders' => $this->repository->markOrderDeleted($store, $wooResourceId),
            default => null,
        };
    }

    private function endpointFor(string $resource): string
    {
        return match ($resource) {
            'categories' => 'products/categories',
            'products' => 'products',
            'customers' => 'customers',
            'orders' => 'orders',
            default => throw new \InvalidArgumentException("Unsupported WooCommerce resource [{$resource}]."),
        };
    }

    private function queryFor(string $resource): array
    {
        return match ($resource) {
            'categories' => ['orderby' => 'id', 'order' => 'asc'],
            'products', 'orders' => ['orderby' => 'date', 'order' => 'desc'],
            'customers' => ['orderby' => 'registered_date', 'order' => 'desc'],
            default => [],
        };
    }

    private function resourceFromWebhook(WooWebhookEvent $event): string
    {
        $resource = $event->resource ?: explode('.', $event->topic)[0];

        return match ($resource) {
            'customer', 'customers' => 'customers',
            'product', 'products' => 'products',
            'order', 'orders' => 'orders',
            default => throw new \InvalidArgumentException("Unsupported WooCommerce webhook resource [{$resource}]."),
        };
    }

    private function isDeletionTopic(string $topic): bool
    {
        return str_contains($topic, '.deleted') || str_contains($topic, '.trash');
    }

    private function assertSupportedResource(string $resource): void
    {
        if (! in_array($resource, self::RESOURCES, true)) {
            throw new \InvalidArgumentException("Unsupported WooCommerce resource [{$resource}].");
        }
    }

    private function updateStoreSyncSettings(WooCommerceStore $store, array $settings): void
    {
        $store->forceFill([
            'sync_settings' => array_merge($store->sync_settings ?? [], $settings),
        ])->save();

        $store->refresh();
    }
}
