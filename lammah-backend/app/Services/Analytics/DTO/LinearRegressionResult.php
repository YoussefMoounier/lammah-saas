<?php

namespace App\Services\Analytics\DTO;

final readonly class LinearRegressionResult
{
    public function __construct(
        public float $slope,
        public float $intercept,
        public float $rSquared,
        public float $standardError,
        public int $sampleSize,
    ) {
    }

    public function predict(float $x): float
    {
        return $this->intercept + ($this->slope * $x);
    }

    public function toArray(): array
    {
        return [
            'slope' => round($this->slope, 6),
            'intercept' => round($this->intercept, 6),
            'r_squared' => round($this->rSquared, 6),
            'standard_error' => round($this->standardError, 6),
            'sample_size' => $this->sampleSize,
        ];
    }
}
