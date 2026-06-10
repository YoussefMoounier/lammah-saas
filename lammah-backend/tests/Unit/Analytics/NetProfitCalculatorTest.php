<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\NetProfitCalculator;
use Tests\TestCase;

class NetProfitCalculatorTest extends TestCase
{
    public function test_it_calculates_true_net_profit_after_costs_fees_refunds_shipping_and_tax(): void
    {
        $calculator = new NetProfitCalculator();

        $gatewayFee = $calculator->gatewayFee(orderTotal: 100, percentFee: 2.5, fixedFee: 1);
        $result = $calculator->calculate(
            grossRevenue: 100,
            productCostTotal: 35,
            gatewayFeeTotal: $gatewayFee,
            shippingCostTotal: 5,
            refundsTotal: 10,
            taxTotal: 3,
        );

        $this->assertEquals(3.5, $gatewayFee);
        $this->assertEquals(43.5, $result->netProfit);
        $this->assertEquals(43.5, $result->marginPercent);
    }
}
