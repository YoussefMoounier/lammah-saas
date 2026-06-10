<?php

namespace Tests\Unit\Operations;

use App\Services\Operations\PriceAdjustmentCalculator;
use Tests\TestCase;

class PriceAdjustmentCalculatorTest extends TestCase
{
    public function test_it_calculates_flat_percent_and_set_price_updates(): void
    {
        $calculator = new PriceAdjustmentCalculator();

        $this->assertSame(105.0, $calculator->calculate(100, 'flat', 5));
        $this->assertSame(110.0, $calculator->calculate(100, 'percent', 10));
        $this->assertSame(89.5, $calculator->calculate(100, 'set', 89.5));
    }

    public function test_it_never_returns_negative_prices(): void
    {
        $this->assertSame(0.0, (new PriceAdjustmentCalculator())->calculate(5, 'flat', -50));
    }
}
