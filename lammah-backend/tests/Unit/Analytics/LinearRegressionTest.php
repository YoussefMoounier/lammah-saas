<?php

namespace Tests\Unit\Analytics;

use App\Services\Analytics\Statistics\LinearRegression;
use Tests\TestCase;

class LinearRegressionTest extends TestCase
{
    public function test_it_fits_a_simple_positive_sales_trend(): void
    {
        $result = (new LinearRegression())->fit([
            ['x' => 1, 'y' => 100],
            ['x' => 2, 'y' => 120],
            ['x' => 3, 'y' => 140],
            ['x' => 4, 'y' => 160],
            ['x' => 5, 'y' => 180],
            ['x' => 6, 'y' => 200],
        ]);

        $this->assertEqualsWithDelta(20.0, $result->slope, 0.0001);
        $this->assertEqualsWithDelta(80.0, $result->intercept, 0.0001);
        $this->assertEqualsWithDelta(220.0, $result->predict(7), 0.0001);
        $this->assertEqualsWithDelta(1.0, $result->rSquared, 0.0001);
    }
}
