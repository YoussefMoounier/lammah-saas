<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WooCommerceStoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'merchant_id' => $this->merchant_id,
            'name' => $this->name,
            'base_url' => $this->base_url,
            'api_version' => $this->api_version,
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'status' => $this->status,
            'last_successful_sync_at' => $this->last_successful_sync_at?->toIso8601String(),
            'last_failed_sync_at' => $this->last_failed_sync_at?->toIso8601String(),
            'last_error' => $this->last_error,
            'connector_status' => $this->connector_status ?? 'not_configured',
            'connector_last_seen_at' => $this->connector_last_seen_at?->toIso8601String(),
            'connector_version' => $this->connector_version,
            'connector_last_error' => $this->connector_last_error,
            'has_connector_token' => filled($this->connector_token_hash),
            'sync_settings' => $this->sync_settings ?? [],
            'metadata' => $this->metadata ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
