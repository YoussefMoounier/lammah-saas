<?php

namespace App\Services\Analytics\Statistics;

use App\Services\Analytics\DTO\LinearRegressionResult;
use InvalidArgumentException;

class LinearRegression
{
    /**
     * @param array<int, array{x: float|int, y: float|int}> $points
     */
    public function fit(array $points): LinearRegressionResult
    {
        if (count($points) === 0) {
            throw new InvalidArgumentException('Linear regression requires at least one data point.');
        }

        $n = count($points);
        $sumX = 0.0;
        $sumY = 0.0;
        $sumXY = 0.0;
        $sumXX = 0.0;

        foreach ($points as $point) {
            $x = (float) $point['x'];
            $y = (float) $point['y'];

            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumXX += $x * $x;
        }

        $denominator = ($n * $sumXX) - ($sumX * $sumX);

        if (abs($denominator) < 0.000001) {
            return new LinearRegressionResult(
                slope: 0.0,
                intercept: $sumY / $n,
                rSquared: 0.0,
                standardError: 0.0,
                sampleSize: $n,
            );
        }

        $slope = (($n * $sumXY) - ($sumX * $sumY)) / $denominator;
        $intercept = ($sumY - ($slope * $sumX)) / $n;
        $meanY = $sumY / $n;
        $ssTotal = 0.0;
        $ssResidual = 0.0;

        foreach ($points as $point) {
            $actual = (float) $point['y'];
            $predicted = $intercept + ($slope * (float) $point['x']);
            $ssTotal += ($actual - $meanY) ** 2;
            $ssResidual += ($actual - $predicted) ** 2;
        }

        $rSquared = $ssTotal <= 0.000001 ? 1.0 : max(0.0, min(1.0, 1 - ($ssResidual / $ssTotal)));
        $standardError = $n > 2 ? sqrt($ssResidual / ($n - 2)) : 0.0;

        return new LinearRegressionResult(
            slope: $slope,
            intercept: $intercept,
            rSquared: $rSquared,
            standardError: $standardError,
            sampleSize: $n,
        );
    }
}
