<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceUpdateBatchResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'store_id' => $this->store_id,
            'requested_by' => $this->requested_by,
            'mode' => $this->mode,
            'value' => (float) $this->value,
            'currency' => $this->currency,
            'target_type' => $this->target_type,
            'target_filters' => $this->target_filters,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'started_at' => $this->started_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'items_count' => $this->whenCounted('items'),
            'items' => PriceUpdateItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
