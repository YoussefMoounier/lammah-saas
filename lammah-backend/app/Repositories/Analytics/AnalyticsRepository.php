<?php

namespace App\Repositories\Analytics;

use App\Models\AiMetricRun;
use App\Models\FraudRiskSignal;
use App\Models\OrderProfitSnapshot;
use App\Models\PaymentGatewayFeeRule;
use App\Models\ProductCost;
use App\Models\RfmCustomerScore;
use App\Models\SalesForecast;
use App\Models\SubscriptionEntitlement;
use App\Models\WooCommerceStore;
use App\Models\WooCustomer;
use App\Models\WooOrder;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository
{
    public function startMetricRun(
        string $merchantId,
        ?string $storeId,
        string $metricType,
        ?CarbonInterface $windowStart = null,
        ?CarbonInterface $windowEnd = null,
        array $parameters = [],
    ): AiMetricRun {
        return AiMetricRun::create([
            'merchant_id' => $merchantId,
            'store_id' => $storeId,
            'metric_type' => $metricType,
            'engine' => 'laravel_native',
            'status' => 'running',
            'input_window_start' => $windowStart,
            'input_window_end' => $windowEnd,
            'parameters' => $parameters,
            'started_at' => now(),
        ]);
    }

    public function finishMetricRun(AiMetricRun $run, array $summary = []): void
    {
        $run->forceFill([
            'status' => 'succeeded',
            'finished_at' => now(),
            'result_summary' => $summary,
            'error_message' => null,
        ])->save();
    }

    public function failMetricRun(AiMetricRun $run, string $message): void
    {
        $run->forceFill([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => $message,
        ])->save();
    }

    public function monthlySalesSeries(WooCommerceStore $store, int $months): array
    {
        $windowStart = now()->startOfMonth()->subMonths($months - 1);

        $rows = DB::table('woo_orders')
            ->leftJoin('order_profit_snapshots', 'order_profit_snapshots.order_id', '=', 'woo_orders.id')
            ->where('woo_orders.store_id', $store->id)
            ->whereNotNull('woo_orders.woo_created_at')
            ->where('woo_orders.woo_created_at', '>=', $windowStart)
            ->whereNotIn('woo_orders.status', ['cancelled', 'canceled', 'failed', 'trash'])
            ->selectRaw("date_trunc('month', woo_orders.woo_created_at)::date as month")
            ->selectRaw('sum(woo_orders.total) as gross_revenue')
            ->selectRaw('sum(coalesce(order_profit_snapshots.net_profit, 0)) as net_profit')
            ->selectRaw('count(*) as order_count')
            ->groupByRaw("date_trunc('month', woo_orders.woo_created_at)::date")
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $series = [];

        for ($i = 0; $i < $months; $i++) {
            $month = $windowStart->copy()->addMonths($i)->toDateString();
            $row = $rows->get($month);

            $series[] = [
                'x' => $i + 1,
                'month' => $month,
                'gross_revenue' => (float) ($row->gross_revenue ?? 0),
                'net_profit' => (float) ($row->net_profit ?? 0),
                'order_count' => (int) ($row->order_count ?? 0),
            ];
        }

        return $series;
    }

    public function saveSalesForecast(WooCommerceStore $store, AiMetricRun $run, array $data): SalesForecast
    {
        return SalesForecast::updateOrCreate(
            [
                'store_id' => $store->id,
                'forecast_month' => $data['forecast_month'],
            ],
            array_merge($data, [
                'merchant_id' => $store->merchant_id,
                'metric_run_id' => $run->id,
                'generated_at' => now(),
            ])
        );
    }

    public function orderForProfit(string $orderId): WooOrder
    {
        return WooOrder::query()
            ->with(['items', 'store'])
            ->findOrFail($orderId);
    }

    public function effectiveProductCost(WooOrder $order, ?string $productId, ?int $wooProductId): ?ProductCost
    {
        if ($productId === null && $wooProductId === null) {
            return null;
        }

        $effectiveDate = ($order->woo_created_at ?: $order->created_at ?: now())->toDateString();

        return ProductCost::query()
            ->where('merchant_id', $order->store->merchant_id)
            ->where(function ($query) use ($order): void {
                $query->whereNull('store_id')->orWhere('store_id', $order->store_id);
            })
            ->where(function ($query) use ($productId, $wooProductId): void {
                $query->when($productId, fn ($inner) => $inner->orWhere('product_id', $productId));
                $query->when($wooProductId, fn ($inner) => $inner->orWhere('woo_product_id', $wooProductId));
            })
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->orderByDesc('store_id')
            ->orderByDesc('effective_from')
            ->first();
    }

    public function effectiveGatewayRule(WooOrder $order): ?PaymentGatewayFeeRule
    {
        $effectiveDate = ($order->woo_created_at ?: $order->created_at ?: now())->toDateString();
        $orderTotal = (float) $order->total;

        return PaymentGatewayFeeRule::query()
            ->where('merchant_id', $order->store->merchant_id)
            ->where('gateway_code', $order->payment_method)
            ->where('is_active', true)
            ->where(function ($query) use ($order): void {
                $query->whereNull('store_id')->orWhere('store_id', $order->store_id);
            })
            ->where('effective_from', '<=', $effectiveDate)
            ->where(function ($query) use ($effectiveDate): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>=', $effectiveDate);
            })
            ->where(function ($query) use ($orderTotal): void {
                $query->whereNull('min_order_amount')->orWhere('min_order_amount', '<=', $orderTotal);
            })
            ->where(function ($query) use ($orderTotal): void {
                $query->whereNull('max_order_amount')->orWhere('max_order_amount', '>=', $orderTotal);
            })
            ->orderByDesc('store_id')
            ->orderByDesc('effective_from')
            ->first();
    }

    public function saveProfitSnapshot(WooOrder $order, array $data): OrderProfitSnapshot
    {
        return OrderProfitSnapshot::updateOrCreate(
            ['order_id' => $order->id],
            array_merge($data, [
                'merchant_id' => $order->store->merchant_id,
                'store_id' => $order->store_id,
                'calculated_at' => now(),
            ])
        );
    }

    public function recentFraudCandidates(WooCommerceStore $store, CarbonInterface $since): EloquentCollection
    {
        return WooOrder::query()
            ->with('customer')
            ->where('store_id', $store->id)
            ->where('woo_created_at', '>=', $since)
            ->whereNotIn('status', ['cancelled', 'canceled', 'failed', 'trash'])
            ->orderBy('woo_created_at')
            ->get();
    }

    public function orderCountForHash(string $storeId, string $hashColumn, ?string $hash, CarbonInterface $since): int
    {
        if ($hash === null) {
            return 0;
        }

        return WooOrder::query()
            ->where('store_id', $storeId)
            ->where($hashColumn, $hash)
            ->where('woo_created_at', '>=', $since)
            ->count();
    }

    public function customerCountForIpHash(string $storeId, ?string $ipHash): int
    {
        if ($ipHash === null) {
            return 0;
        }

        return WooOrder::query()
            ->where('store_id', $storeId)
            ->where('customer_ip_hash', $ipHash)
            ->whereNotNull('customer_id')
            ->distinct('customer_id')
            ->count('customer_id');
    }

    public function trialCountForEmailHash(string $storeId, ?string $emailHash, CarbonInterface $since): int
    {
        if ($emailHash === null) {
            return 0;
        }

        return SubscriptionEntitlement::query()
            ->where('store_id', $storeId)
            ->where('status', 'trial')
            ->where('created_at', '>=', $since)
            ->whereHas('customer', fn ($query) => $query->where('email_hash', $emailHash))
            ->count();
    }

    public function saveFraudSignal(WooOrder $order, AiMetricRun $run, array $data): FraudRiskSignal
    {
        return FraudRiskSignal::updateOrCreate(
            [
                'merchant_id' => $order->store->merchant_id,
                'store_id' => $order->store_id,
                'order_id' => $order->id,
                'signal_type' => $data['signal_type'],
            ],
            [
                'customer_id' => $order->customer_id,
                'metric_run_id' => $run->id,
                'risk_score' => $data['risk_score'],
                'severity' => $data['severity'],
                'evidence' => $data['evidence'],
                'status' => 'open',
                'first_seen_at' => $order->woo_created_at ?: now(),
                'last_seen_at' => now(),
            ]
        );
    }

    public function rfmRows(WooCommerceStore $store, CarbonInterface $since): array
    {
        return WooCustomer::query()
            ->where('store_id', $store->id)
            ->whereHas('orders', fn ($query) => $query->where('woo_created_at', '>=', $since))
            ->withMax('orders as last_order_at', 'woo_created_at')
            ->withCount(['orders as frequency_orders' => fn ($query) => $query->where('woo_created_at', '>=', $since)])
            ->withSum(['orders as monetary_value' => fn ($query) => $query->where('woo_created_at', '>=', $since)], 'total')
            ->get()
            ->all();
    }

    public function latestSubscriptionExpiry(string $customerId): ?Carbon
    {
        $expiry = SubscriptionEntitlement::query()
            ->where('customer_id', $customerId)
            ->max('expires_at');

        return $expiry === null ? null : Carbon::parse($expiry);
    }

    public function saveRfmScore(WooCustomer $customer, WooCommerceStore $store, AiMetricRun $run, array $data): RfmCustomerScore
    {
        return RfmCustomerScore::updateOrCreate(
            [
                'customer_id' => $customer->id,
                'metric_run_id' => $run->id,
            ],
            array_merge($data, [
                'merchant_id' => $store->merchant_id,
                'store_id' => $store->id,
                'calculated_at' => now(),
            ])
        );
    }
}
