<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WooCommerceStore extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'woocommerce_stores';

    protected $guarded = [];

    protected $casts = [
        'consumer_key_encrypted' => 'encrypted',
        'consumer_secret_encrypted' => 'encrypted',
        'webhook_secret_encrypted' => 'encrypted',
        'sync_settings' => 'array',
        'metadata' => 'array',
        'last_successful_sync_at' => 'datetime',
        'last_failed_sync_at' => 'datetime',
        'connector_last_seen_at' => 'datetime',
    ];

    public function consumerKey(): string
    {
        return (string) $this->consumer_key_encrypted;
    }

    public function consumerSecret(): string
    {
        return (string) $this->consumer_secret_encrypted;
    }

    public function webhookSecret(): ?string
    {
        return $this->webhook_secret_encrypted ?: null;
    }

    public function orders(): HasMany
    {
        return $this->hasMany(WooOrder::class, 'store_id');
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class, 'merchant_id');
    }
}
