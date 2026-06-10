<?php

namespace App\Services\Analytics;

use App\Models\OrderProfitSnapshot;
use App\Models\WooOrder;
use App\Repositories\Analytics\AnalyticsRepository;

class NetProfitAnalyticsService
{
    public function __construct(
        private readonly AnalyticsRepository $repository,
        private readonly NetProfitCalculator $calculator,
    ) {
    }

    public function calculateForOrder(string|WooOrder $order): OrderProfitSnapshot
    {
        $order = $order instanceof WooOrder
            ? $order->loadMissing(['items', 'store'])
            : $this->repository->orderForProfit($order);

        $lineBreakdown = [];
        $productCostTotal = 0.0;

        foreach ($order->items as $item) {
            $cost = $this->repository->effectiveProductCost($order, $item->product_id, $item->woo_product_id);
            $unitCost = (float) ($cost?->cost_amount ?? 0);
            $quantity = (float) $item->quantity;
            $lineCost = round($unitCost * $quantity, 4);
            $productCostTotal += $lineCost;

            $lineBreakdown[] = [
                'order_item_id' => $item->id,
                'woo_order_item_id' => $item->woo_order_item_id,
                'product_id' => $item->product_id,
                'woo_product_id' => $item->woo_product_id,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'line_cost' => $lineCost,
            ];
        }

        $gatewayRule = $this->repository->effectiveGatewayRule($order);
        $gatewayFeeTotal = $gatewayRule === null
            ? 0.0
            : $this->calculator->gatewayFee((float) $order->total, (float) $gatewayRule->percent_fee, (float) $gatewayRule->fixed_fee);

        $result = $this->calculator->calculate(
            grossRevenue: (float) $order->total,
            productCostTotal: $productCostTotal,
            gatewayFeeTotal: $gatewayFeeTotal,
            shippingCostTotal: (float) $order->shipping_total,
            refundsTotal: (float) $order->refund_total,
            taxTotal: (float) $order->tax_total,
            lineBreakdown: $lineBreakdown,
        );

        return $this->repository->saveProfitSnapshot($order, [
            'gross_revenue' => $this->money($result->grossRevenue),
            'product_cost_total' => $this->money($result->productCostTotal),
            'gateway_fee_total' => $this->money($result->gatewayFeeTotal),
            'shipping_cost_total' => $this->money($result->shippingCostTotal),
            'refunds_total' => $this->money($result->refundsTotal),
            'tax_total' => $this->money($result->taxTotal),
            'net_profit' => $this->money($result->netProfit),
            'margin_percent' => $result->marginPercent,
            'currency' => $order->currency,
            'calculation_payload' => array_merge($result->payload, [
                'gateway_rule_id' => $gatewayRule?->id,
                'gateway_percent_fee' => $gatewayRule?->percent_fee,
                'gateway_fixed_fee' => $gatewayRule?->fixed_fee,
            ]),
        ]);
    }

    private function money(float $value): string
    {
        return number_format($value, 4, '.', '');
    }
}
