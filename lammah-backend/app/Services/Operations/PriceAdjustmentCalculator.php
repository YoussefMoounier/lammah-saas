<?php

namespace App\Services\Operations;

use InvalidArgumentException;

class PriceAdjustmentCalculator
{
    public function calculate(float $currentPrice, string $mode, float $value): float
    {
        $newPrice = match ($mode) {
            'flat' => $currentPrice + $value,
            'percent' => $currentPrice * (1 + ($value / 100)),
            'set' => $value,
            default => throw new InvalidArgumentException("Unsupported price update mode [{$mode}]."),
        };

        return round(max(0, $newPrice), 4);
    }
}
