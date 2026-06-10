<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderProfitSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'store_id' => $this->store_id,
            'order_id' => $this->order_id,
            'gross_revenue' => (float) $this->gross_revenue,
            'product_cost_total' => (float) $this->product_cost_total,
            'gateway_fee_total' => (float) $this->gateway_fee_total,
            'shipping_cost_total' => (float) $this->shipping_cost_total,
            'refunds_total' => (float) $this->refunds_total,
            'tax_total' => (float) $this->tax_total,
            'net_profit' => (float) $this->net_profit,
            'margin_percent' => $this->margin_percent === null ? null : (float) $this->margin_percent,
            'currency' => $this->currency,
            'calculation_payload' => $this->calculation_payload,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
