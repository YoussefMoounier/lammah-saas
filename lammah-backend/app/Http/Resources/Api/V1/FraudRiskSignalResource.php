<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FraudRiskSignalResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'store_id' => $this->store_id,
            'customer_id' => $this->customer_id,
            'order_id' => $this->order_id,
            'risk_score' => (float) $this->risk_score,
            'severity' => $this->severity,
            'signal_type' => $this->signal_type,
            'evidence' => $this->evidence,
            'status' => $this->status,
            'first_seen_at' => $this->first_seen_at?->toIso8601String(),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'reviewed_by' => $this->reviewed_by,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }
}
