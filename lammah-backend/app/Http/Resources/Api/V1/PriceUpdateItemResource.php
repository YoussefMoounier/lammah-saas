<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceUpdateItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'batch_id' => $this->batch_id,
            'product_id' => $this->product_id,
            'woo_product_id' => $this->woo_product_id,
            'old_regular_price' => $this->old_regular_price === null ? null : (float) $this->old_regular_price,
            'old_sale_price' => $this->old_sale_price === null ? null : (float) $this->old_sale_price,
            'new_regular_price' => $this->new_regular_price === null ? null : (float) $this->new_regular_price,
            'new_sale_price' => $this->new_sale_price === null ? null : (float) $this->new_sale_price,
            'currency' => $this->currency,
            'status' => $this->status,
            'error_message' => $this->error_message,
            'applied_at' => $this->applied_at?->toIso8601String(),
        ];
    }
}
