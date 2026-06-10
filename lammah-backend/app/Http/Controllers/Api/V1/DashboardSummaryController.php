<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\V1\Concerns\AuthorizesMerchantAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardSummaryController extends Controller
{
    use AuthorizesMerchantAccess;

    public function __invoke(Request $request, string $merchant): JsonResponse
    {
        $this->authorizeMerchant($request, $merchant, 'analytics.view');
        $validated = $request->validate([
            'store_id' => ['nullable', 'string'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $storeIds = DB::table('woocommerce_stores')
            ->where('merchant_id', $merchant)
            ->when($validated['store_id'] ?? null, fn ($query, string $storeId) => $query->where('id', $storeId))
            ->pluck('id');

        $days = (int) ($validated['days'] ?? 30);
        $since = now()->subDays($days);

        $grossRevenue = DB::table('woo_orders')
            ->whereIn('store_id', $storeIds)
            ->where('woo_created_at', '>=', $since)
            ->whereNotIn('status', ['cancelled', 'canceled', 'failed', 'trash'])
            ->sum('total');

        $netProfit = DB::table('order_profit_snapshots')
            ->whereIn('store_id', $storeIds)
            ->where('calculated_at', '>=', $since)
            ->sum('net_profit');

        $latestForecast = DB::table('sales_forecasts')
            ->whereIn('store_id', $storeIds)
            ->orderByDesc('generated_at')
            ->first();

        return response()->json([
            'data' => [
                'period' => [
                    'days' => $days,
                    'starts_at' => $since->toIso8601String(),
                    'ends_at' => now()->toIso8601String(),
                ],
                'gross_revenue' => round((float) $grossRevenue, 4),
                'net_profit' => round((float) $netProfit, 4),
                'open_fraud_signals' => DB::table('fraud_risk_signals')
                    ->where('merchant_id', $merchant)
                    ->whereIn('store_id', $storeIds)
                    ->whereIn('status', ['open', 'reviewing'])
                    ->count(),
                'high_churn_customers' => DB::table('rfm_customer_scores')
                    ->where('merchant_id', $merchant)
                    ->whereIn('store_id', $storeIds)
                    ->where('churn_probability', '>=', 0.65)
                    ->count(),
                'active_shifts' => DB::table('shifts')
                    ->where('merchant_id', $merchant)
                    ->where('status', 'active')
                    ->count(),
                'queued_price_updates' => DB::table('price_update_batches')
                    ->where('merchant_id', $merchant)
                    ->whereIn('status', ['queued', 'running'])
                    ->count(),
                'latest_forecast' => $latestForecast,
            ],
        ]);
    }
}
