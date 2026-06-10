<?php

namespace App\Services\Analytics\DTO;

final readonly class RfmScoreResult
{
    public function __construct(
        public int $recencyDays,
        public int $frequencyOrders,
        public float $monetaryValue,
        public int $rScore,
        public int $fScore,
        public int $mScore,
        public string $segment,
        public float $churnProbability,
        public array $signals,
    ) {
    }
}
