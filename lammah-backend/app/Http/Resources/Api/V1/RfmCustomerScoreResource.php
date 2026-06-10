<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RfmCustomerScoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer_id' => $this->customer_id,
            'store_id' => $this->store_id,
            'recency_days' => $this->recency_days,
            'frequency_orders' => $this->frequency_orders,
            'monetary_value' => (float) $this->monetary_value,
            'r_score' => $this->r_score,
            'f_score' => $this->f_score,
            'm_score' => $this->m_score,
            'segment' => $this->segment,
            'subscription_expires_at' => $this->subscription_expires_at?->toIso8601String(),
            'churn_probability' => $this->churn_probability === null ? null : (float) $this->churn_probability,
            'signals' => $this->signals,
            'calculated_at' => $this->calculated_at?->toIso8601String(),
        ];
    }
}
