<?php

namespace App\Repositories\WooCommerce;

use App\Models\SyncRun;
use App\Models\WooCategory;
use App\Models\WooCommerceStore;
use App\Models\WooCustomer;
use App\Models\WooOrder;
use App\Models\WooOrderItem;
use App\Models\WooProduct;
use App\Models\WooWebhookEvent;
use App\Support\Security\PrivacyHasher;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WooCommerceSyncRepository
{
    public function startSyncRun(WooCommerceStore $store, string $type, array $metadata = []): SyncRun
    {
        return SyncRun::create([
            'store_id' => $store->id,
            'sync_type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'metadata' => $metadata,
        ]);
    }

    public function finishSyncRun(SyncRun $run, array $totals = []): void
    {
        $run->forceFill([
            'status' => 'succeeded',
            'finished_at' => now(),
            'totals' => $totals,
            'error_message' => null,
        ])->save();
    }

    public function failSyncRun(SyncRun $run, string $message, array $totals = []): void
    {
        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'totals' => $totals,
            'error_message' => $message,
        ])->save();
    }

    public function upsertCustomer(WooCommerceStore $store, array $payload): WooCustomer
    {
        $billing = Arr::get($payload, 'billing', []);
        $email = Arr::get($payload, 'email') ?: Arr::get($billing, 'email');
        $phone = Arr::get($billing, 'phone');
        $customer = $this->findOrNewCustomer($store, $payload, $email);

        $customer->fill([
            'store_id' => $store->id,
            'woo_customer_id' => $this->nullableInt(Arr::get($payload, 'id')),
            'email_encrypted' => $email,
            'email_hash' => PrivacyHasher::email($email),
            'phone_encrypted' => $phone,
            'phone_hash' => PrivacyHasher::phone($phone),
            'first_name_encrypted' => Arr::get($payload, 'first_name') ?: Arr::get($billing, 'first_name'),
            'last_name_encrypted' => Arr::get($payload, 'last_name') ?: Arr::get($billing, 'last_name'),
            'username' => Arr::get($payload, 'username'),
            'woo_created_at' => $this->date(Arr::get($payload, 'date_created_gmt') ?: Arr::get($payload, 'date_created')),
            'woo_updated_at' => $this->date(Arr::get($payload, 'date_modified_gmt') ?: Arr::get($payload, 'date_modified')),
            'total_spent' => $this->money(Arr::get($payload, 'total_spent')),
            'orders_count' => (int) (Arr::get($payload, 'orders_count') ?? 0),
            'raw_payload' => $payload,
            'synced_at' => now(),
        ]);

        $customer->save();

        return $customer;
    }

    public function upsertCategory(WooCommerceStore $store, array $payload): WooCategory
    {
        return WooCategory::updateOrCreate(
            [
                'store_id' => $store->id,
                'woo_category_id' => (int) Arr::get($payload, 'id'),
            ],
            [
                'parent_woo_category_id' => $this->nullableInt(Arr::get($payload, 'parent')),
                'name' => (string) Arr::get($payload, 'name'),
                'slug' => Arr::get($payload, 'slug'),
                'description' => Arr::get($payload, 'description'),
                'display' => Arr::get($payload, 'display'),
                'raw_payload' => $payload,
                'synced_at' => now(),
            ]
        );
    }

    public function upsertProduct(WooCommerceStore $store, array $payload): WooProduct
    {
        $product = WooProduct::updateOrCreate(
            [
                'store_id' => $store->id,
                'woo_product_id' => (int) Arr::get($payload, 'id'),
            ],
            [
                'sku' => Arr::get($payload, 'sku') ?: null,
                'name' => (string) Arr::get($payload, 'name'),
                'slug' => Arr::get($payload, 'slug'),
                'type' => Arr::get($payload, 'type', 'simple'),
                'status' => Arr::get($payload, 'status', 'publish'),
                'catalog_visibility' => Arr::get($payload, 'catalog_visibility'),
                'regular_price' => $this->nullableMoney(Arr::get($payload, 'regular_price')),
                'sale_price' => $this->nullableMoney(Arr::get($payload, 'sale_price')),
                'current_price' => $this->nullableMoney(Arr::get($payload, 'price')),
                'currency' => $store->currency,
                'stock_status' => Arr::get($payload, 'stock_status'),
                'stock_quantity' => $this->nullableInt(Arr::get($payload, 'stock_quantity')),
                'manages_stock' => (bool) Arr::get($payload, 'manage_stock', false),
                'is_virtual' => (bool) Arr::get($payload, 'virtual', true),
                'is_downloadable' => (bool) Arr::get($payload, 'downloadable', false),
                'attributes' => Arr::get($payload, 'attributes', []),
                'raw_payload' => $payload,
                'synced_at' => now(),
            ]
        );

        $this->syncProductCategories($store, $product, Arr::get($payload, 'categories', []));

        return $product;
    }

    public function upsertOrder(WooCommerceStore $store, array $payload): WooOrder
    {
        return DB::transaction(function () use ($store, $payload): WooOrder {
            $customer = $this->upsertOrderCustomerStub($store, $payload);
            $billing = Arr::get($payload, 'billing', []);
            $shipping = Arr::get($payload, 'shipping', []);
            $email = Arr::get($billing, 'email');
            $phone = Arr::get($billing, 'phone');
            $ip = Arr::get($payload, 'customer_ip_address');

            $order = WooOrder::updateOrCreate(
                [
                    'store_id' => $store->id,
                    'woo_order_id' => (int) Arr::get($payload, 'id'),
                ],
                [
                    'customer_id' => $customer?->id,
                    'woo_order_key' => Arr::get($payload, 'order_key'),
                    'status' => Arr::get($payload, 'status', 'pending'),
                    'currency' => Arr::get($payload, 'currency', $store->currency),
                    'subtotal' => $this->orderSubtotal($payload),
                    'discount_total' => $this->money(Arr::get($payload, 'discount_total')),
                    'shipping_total' => $this->money(Arr::get($payload, 'shipping_total')),
                    'tax_total' => $this->money(Arr::get($payload, 'total_tax')),
                    'fee_total' => $this->feeTotal($payload),
                    'refund_total' => $this->refundTotal($payload),
                    'total' => $this->money(Arr::get($payload, 'total')),
                    'payment_method' => Arr::get($payload, 'payment_method'),
                    'payment_method_title' => Arr::get($payload, 'payment_method_title'),
                    'transaction_id' => Arr::get($payload, 'transaction_id'),
                    'customer_email_encrypted' => $email,
                    'customer_email_hash' => PrivacyHasher::email($email),
                    'customer_phone_encrypted' => $phone,
                    'customer_phone_hash' => PrivacyHasher::phone($phone),
                    'customer_ip_encrypted' => $ip,
                    'customer_ip_hash' => PrivacyHasher::ip($ip),
                    'billing_country' => Arr::get($billing, 'country'),
                    'woo_created_at' => $this->date(Arr::get($payload, 'date_created_gmt') ?: Arr::get($payload, 'date_created')),
                    'paid_at' => $this->date(Arr::get($payload, 'date_paid_gmt') ?: Arr::get($payload, 'date_paid')),
                    'completed_at' => $this->date(Arr::get($payload, 'date_completed_gmt') ?: Arr::get($payload, 'date_completed')),
                    'billing_snapshot' => $billing,
                    'shipping_snapshot' => $shipping,
                    'raw_payload' => $payload,
                    'synced_at' => now(),
                ]
            );

            $this->upsertOrderItems($store, $order, Arr::get($payload, 'line_items', []));

            return $order;
        });
    }

    public function markProductDeleted(WooCommerceStore $store, int|string $wooProductId): void
    {
        WooProduct::query()
            ->where('store_id', $store->id)
            ->where('woo_product_id', (int) $wooProductId)
            ->update(['status' => 'deleted', 'deleted_at' => now(), 'synced_at' => now()]);
    }

    public function markOrderDeleted(WooCommerceStore $store, int|string $wooOrderId): void
    {
        WooOrder::query()
            ->where('store_id', $store->id)
            ->where('woo_order_id', (int) $wooOrderId)
            ->update(['status' => 'deleted', 'synced_at' => now()]);
    }

    public function markCustomerDeleted(WooCommerceStore $store, int|string $wooCustomerId): void
    {
        WooCustomer::query()
            ->where('store_id', $store->id)
            ->where('woo_customer_id', (int) $wooCustomerId)
            ->update(['deleted_at' => now(), 'synced_at' => now()]);
    }

    public function recordWebhookEvent(
        WooCommerceStore $store,
        array $headers,
        array $payload,
        string $rawPayload,
        bool $signatureValid
    ): WooWebhookEvent {
        $eventId = $this->header($headers, 'x-wc-webhook-delivery-id')
            ?? $this->header($headers, 'x-wc-webhook-id')
            ?? hash('sha256', $rawPayload);

        return WooWebhookEvent::updateOrCreate(
            [
                'store_id' => $store->id,
                'event_id' => $eventId,
            ],
            [
                'topic' => $this->header($headers, 'x-wc-webhook-topic') ?? 'unknown',
                'resource' => $this->header($headers, 'x-wc-webhook-resource'),
                'woo_resource_id' => (string) (Arr::get($payload, 'id') ?? ''),
                'signature_valid' => $signatureValid,
                'signature_hash' => PrivacyHasher::value($this->header($headers, 'x-wc-webhook-signature')),
                'headers' => $headers,
                'payload' => $payload,
                'received_at' => now(),
                'status' => $signatureValid ? 'pending' : 'failed',
                'error_message' => $signatureValid ? null : 'Invalid WooCommerce webhook signature.',
            ]
        );
    }

    public function markWebhookProcessing(WooWebhookEvent $event): void
    {
        $event->forceFill(['status' => 'processing'])->save();
    }

    public function markWebhookProcessed(WooWebhookEvent $event): void
    {
        $event->forceFill([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ])->save();
    }

    public function markWebhookFailed(WooWebhookEvent $event, string $message): void
    {
        $event->forceFill([
            'status' => 'failed',
            'retry_count' => $event->retry_count + 1,
            'error_message' => $message,
        ])->save();
    }

    private function findOrNewCustomer(WooCommerceStore $store, array $payload, ?string $email): WooCustomer
    {
        $wooCustomerId = $this->nullableInt(Arr::get($payload, 'id'));

        if ($wooCustomerId !== null) {
            return WooCustomer::firstOrNew([
                'store_id' => $store->id,
                'woo_customer_id' => $wooCustomerId,
            ]);
        }

        $emailHash = PrivacyHasher::email($email);

        if ($emailHash !== null) {
            return WooCustomer::firstOrNew([
                'store_id' => $store->id,
                'email_hash' => $emailHash,
            ]);
        }

        return new WooCustomer(['store_id' => $store->id]);
    }

    private function upsertOrderCustomerStub(WooCommerceStore $store, array $orderPayload): ?WooCustomer
    {
        $wooCustomerId = $this->nullableInt(Arr::get($orderPayload, 'customer_id'));
        $billing = Arr::get($orderPayload, 'billing', []);
        $email = Arr::get($billing, 'email');

        if ($wooCustomerId === null && blank($email)) {
            return null;
        }

        return $this->upsertCustomer($store, [
            'id' => $wooCustomerId,
            'email' => $email,
            'first_name' => Arr::get($billing, 'first_name'),
            'last_name' => Arr::get($billing, 'last_name'),
            'billing' => $billing,
        ]);
    }

    private function syncProductCategories(WooCommerceStore $store, WooProduct $product, array $categories): void
    {
        $wooCategoryIds = collect($categories)
            ->pluck('id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($wooCategoryIds === []) {
            $product->categories()->sync([]);

            return;
        }

        $categoryIds = WooCategory::query()
            ->where('store_id', $store->id)
            ->whereIn('woo_category_id', $wooCategoryIds)
            ->pluck('id')
            ->all();

        $product->categories()->sync($categoryIds);
    }

    private function upsertOrderItems(WooCommerceStore $store, WooOrder $order, array $items): void
    {
        $seen = [];

        foreach ($items as $item) {
            $wooItemId = (int) Arr::get($item, 'id');
            $wooProductId = $this->nullableInt(Arr::get($item, 'product_id'));
            $productId = $wooProductId === null
                ? null
                : WooProduct::query()
                    ->where('store_id', $store->id)
                    ->where('woo_product_id', $wooProductId)
                    ->value('id');

            $seen[] = $wooItemId;

            WooOrderItem::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'woo_order_item_id' => $wooItemId,
                ],
                [
                    'store_id' => $store->id,
                    'product_id' => $productId,
                    'woo_product_id' => $wooProductId,
                    'woo_variation_id' => $this->nullableInt(Arr::get($item, 'variation_id')),
                    'name' => (string) Arr::get($item, 'name'),
                    'sku' => Arr::get($item, 'sku') ?: null,
                    'quantity' => $this->money(Arr::get($item, 'quantity', 1)),
                    'subtotal' => $this->money(Arr::get($item, 'subtotal')),
                    'total' => $this->money(Arr::get($item, 'total')),
                    'tax_total' => $this->money(Arr::get($item, 'total_tax')),
                    'currency' => $order->currency,
                    'metadata' => Arr::get($item, 'meta_data', []),
                    'raw_payload' => $item,
                ]
            );
        }

        if ($seen !== []) {
            WooOrderItem::query()
                ->where('order_id', $order->id)
                ->whereNotIn('woo_order_item_id', $seen)
                ->delete();
        }
    }

    private function orderSubtotal(array $payload): string
    {
        $lineSubtotal = collect(Arr::get($payload, 'line_items', []))
            ->sum(fn (array $item): float => (float) Arr::get($item, 'subtotal', 0));

        return number_format($lineSubtotal, 4, '.', '');
    }

    private function feeTotal(array $payload): string
    {
        return number_format(
            collect(Arr::get($payload, 'fee_lines', []))->sum(fn (array $fee): float => (float) Arr::get($fee, 'total', 0)),
            4,
            '.',
            ''
        );
    }

    private function refundTotal(array $payload): string
    {
        return number_format(
            collect(Arr::get($payload, 'refunds', []))->sum(fn (array $refund): float => abs((float) Arr::get($refund, 'total', 0))),
            4,
            '.',
            ''
        );
    }

    private function nullableMoney(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->money($value);
    }

    private function money(mixed $value): string
    {
        return number_format((float) ($value ?? 0), 4, '.', '');
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        return (int) $value;
    }

    private function date(?string $value): ?Carbon
    {
        return blank($value) ? null : Carbon::parse($value);
    }

    private function header(array $headers, string $name): ?string
    {
        $value = $headers[strtolower($name)] ?? $headers[$name] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        return $value === null ? null : (string) $value;
    }
}
