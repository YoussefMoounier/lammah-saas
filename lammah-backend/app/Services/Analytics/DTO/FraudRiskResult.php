<?php

namespace App\Services\Analytics\DTO;

final readonly class FraudRiskResult
{
    public function __construct(
        public float $riskScore,
        public string $severity,
        public string $signalType,
        public array $evidence,
    ) {
    }
}
