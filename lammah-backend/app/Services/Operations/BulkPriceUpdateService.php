<?php

namespace App\Services\Operations;

use App\Jobs\Operations\ProcessPriceUpdateBatchJob;
use App\Models\PriceUpdateBatch;
use App\Models\PriceUpdateItem;
use App\Models\WooCommerceStore;
use App\Models\WooProduct;
use App\Services\WooCommerce\Contracts\WooCommerceClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class BulkPriceUpdateService
{
    public function __construct(
        private readonly PriceAdjustmentCalculator $calculator,
        private readonly WooCommerceClient $client,
    ) {
    }

    public function createBatch(WooCommerceStore $store, ?string $requestedBy, array $data): PriceUpdateBatch
    {
        $executeNow = (bool) ($data['execute_now'] ?? true);
        $scheduledAt = isset($data['scheduled_at']) ? Carbon::parse($data['scheduled_at']) : null;

        $batch = DB::transaction(function () use ($store, $requestedBy, $data, $executeNow, $scheduledAt): PriceUpdateBatch {
            $batch = PriceUpdateBatch::create([
                'merchant_id' => $store->merchant_id,
                'store_id' => $store->id,
                'requested_by' => $requestedBy,
                'mode' => $data['mode'],
                'value' => $data['value'],
                'currency' => strtoupper($data['currency'] ?? $store->currency),
                'target_type' => $data['target_type'],
                'target_filters' => $data['target_filters'] ?? [],
                'status' => $executeNow || $scheduledAt ? 'queued' : 'draft',
                'scheduled_at' => $scheduledAt,
            ]);

            $this->createBatchItems($batch);

            return $batch->loadCount('items');
        });

        if ($executeNow || $scheduledAt !== null) {
            $job = ProcessPriceUpdateBatchJob::dispatch($batch->id);

            if ($scheduledAt !== null && $scheduledAt->isFuture()) {
                $job->delay($scheduledAt);
            }
        }

        return $batch;
    }

    public function processBatch(PriceUpdateBatch $batch): PriceUpdateBatch
    {
        $batch->loadMissing(['store', 'items.product']);
        $batch->forceFill(['status' => 'running', 'started_at' => now(), 'error_message' => null])->save();
        $failed = 0;

        foreach ($batch->items as $item) {
            try {
                $this->applyItem($batch, $item);
            } catch (Throwable $exception) {
                $failed++;
                $item->forceFill([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ])->save();
            }
        }

        $batch->forceFill([
            'status' => $failed > 0 ? 'failed' : 'completed',
            'completed_at' => now(),
            'error_message' => $failed > 0 ? "{$failed} price update item(s) failed." : null,
        ])->save();

        return $batch->refresh();
    }

    private function createBatchItems(PriceUpdateBatch $batch): void
    {
        $products = $this->targetProducts($batch)->get();

        foreach ($products as $product) {
            $baseRegular = $product->regular_price ?? $product->current_price ?? 0;
            $newRegular = $this->calculator->calculate((float) $baseRegular, $batch->mode, (float) $batch->value);
            $newSale = $product->sale_price === null
                ? null
                : $this->calculator->calculate((float) $product->sale_price, $batch->mode, (float) $batch->value);

            PriceUpdateItem::create([
                'batch_id' => $batch->id,
                'product_id' => $product->id,
                'woo_product_id' => $product->woo_product_id,
                'old_regular_price' => $product->regular_price,
                'old_sale_price' => $product->sale_price,
                'new_regular_price' => number_format($newRegular, 4, '.', ''),
                'new_sale_price' => $newSale === null ? null : number_format($newSale, 4, '.', ''),
                'currency' => $batch->currency ?? $product->currency,
                'rollback_payload' => [
                    'regular_price' => $product->regular_price,
                    'sale_price' => $product->sale_price,
                ],
            ]);
        }
    }

    private function targetProducts(PriceUpdateBatch $batch): Builder
    {
        $filters = $batch->target_filters ?? [];
        $query = WooProduct::query()->where('store_id', $batch->store_id);

        if (($filters['status'] ?? null) !== null) {
            $query->where('status', $filters['status']);
        } else {
            $query->whereIn('status', ['publish', 'private']);
        }

        match ($batch->target_type) {
            'category' => $query->whereHas('categories', function (Builder $categoryQuery) use ($filters): void {
                $categoryIds = $filters['category_ids'] ?? [];
                $wooCategoryIds = $filters['woo_category_ids'] ?? $categoryIds;

                $categoryQuery->whereIn('woo_categories.id', $categoryIds)
                    ->orWhereIn('woo_categories.woo_category_id', $wooCategoryIds);
            }),
            'product' => $query->where(function (Builder $productQuery) use ($filters): void {
                $productQuery->whereIn('id', $filters['product_ids'] ?? [])
                    ->orWhereIn('woo_product_id', $filters['woo_product_ids'] ?? []);
            }),
            'query' => $query->when($filters['search'] ?? null, function (Builder $searchQuery, string $search): void {
                $searchQuery->where(function (Builder $inner) use ($search): void {
                    $inner->where('name', 'ilike', "%{$search}%")
                        ->orWhere('sku', 'ilike', "%{$search}%");
                });
            }),
            default => $query,
        };

        return $query->orderBy('name');
    }

    private function applyItem(PriceUpdateBatch $batch, PriceUpdateItem $item): void
    {
        if ($item->product === null || $item->woo_product_id === null) {
            throw new \RuntimeException('Cannot apply price update because the local product link is missing.');
        }

        $payload = [
            'regular_price' => $this->wooMoney($item->new_regular_price),
        ];

        if ($item->new_sale_price !== null) {
            $payload['sale_price'] = $this->wooMoney($item->new_sale_price);
        }

        $this->client->put($batch->store, "products/{$item->woo_product_id}", $payload);

        $item->product->forceFill([
            'regular_price' => $item->new_regular_price,
            'sale_price' => $item->new_sale_price,
            'current_price' => $item->new_sale_price ?? $item->new_regular_price,
            'synced_at' => now(),
        ])->save();

        $item->forceFill([
            'status' => 'applied',
            'applied_at' => now(),
            'error_message' => null,
        ])->save();
    }

    private function wooMoney(float|string|null $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
