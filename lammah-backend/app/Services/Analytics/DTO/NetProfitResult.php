<?php

namespace App\Services\Analytics\DTO;

final readonly class NetProfitResult
{
    public function __construct(
        public float $grossRevenue,
        public float $productCostTotal,
        public float $gatewayFeeTotal,
        public float $shippingCostTotal,
        public float $refundsTotal,
        public float $taxTotal,
        public float $netProfit,
        public ?float $marginPercent,
        public array $payload,
    ) {
    }
}
