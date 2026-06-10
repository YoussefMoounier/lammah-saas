<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'user_id' => $this->user_id,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'status' => $this->status,
            'opening_cash' => $this->opening_cash === null ? null : (float) $this->opening_cash,
            'closing_cash' => $this->closing_cash === null ? null : (float) $this->closing_cash,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'attributions_count' => $this->whenCounted('attributions'),
        ];
    }
}
