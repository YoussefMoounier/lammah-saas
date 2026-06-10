<?php

namespace App\Services\Analytics;

use App\Services\Analytics\DTO\NetProfitResult;

class NetProfitCalculator
{
    public function calculate(
        float $grossRevenue,
        float $productCostTotal,
        float $gatewayFeeTotal,
        float $shippingCostTotal = 0.0,
        float $refundsTotal = 0.0,
        float $taxTotal = 0.0,
        array $lineBreakdown = [],
    ): NetProfitResult {
        $netProfit = $grossRevenue
            - $productCostTotal
            - $gatewayFeeTotal
            - $shippingCostTotal
            - $refundsTotal
            - $taxTotal;

        $marginPercent = $grossRevenue > 0
            ? ($netProfit / $grossRevenue) * 100
            : null;

        return new NetProfitResult(
            grossRevenue: round($grossRevenue, 4),
            productCostTotal: round($productCostTotal, 4),
            gatewayFeeTotal: round($gatewayFeeTotal, 4),
            shippingCostTotal: round($shippingCostTotal, 4),
            refundsTotal: round($refundsTotal, 4),
            taxTotal: round($taxTotal, 4),
            netProfit: round($netProfit, 4),
            marginPercent: $marginPercent === null ? null : round($marginPercent, 4),
            payload: ['line_items' => $lineBreakdown],
        );
    }

    public function gatewayFee(float $orderTotal, float $percentFee, float $fixedFee = 0.0): float
    {
        return round(($orderTotal * ($percentFee / 100)) + $fixedFee, 4);
    }
}
