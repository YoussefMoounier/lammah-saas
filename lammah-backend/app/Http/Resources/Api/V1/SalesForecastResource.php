<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalesForecastResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'store_id' => $this->store_id,
            'forecast_month' => $this->forecast_month?->toDateString(),
            'training_months' => $this->training_months,
            'gross_revenue_forecast' => (float) $this->gross_revenue_forecast,
            'net_profit_forecast' => $this->net_profit_forecast === null ? null : (float) $this->net_profit_forecast,
            'order_count_forecast' => $this->order_count_forecast,
            'confidence_low' => $this->confidence_low === null ? null : (float) $this->confidence_low,
            'confidence_high' => $this->confidence_high === null ? null : (float) $this->confidence_high,
            'model_coefficients' => $this->model_coefficients,
            'source_window_start' => $this->source_window_start?->toDateString(),
            'source_window_end' => $this->source_window_end?->toDateString(),
            'generated_at' => $this->generated_at?->toIso8601String(),
        ];
    }
}
