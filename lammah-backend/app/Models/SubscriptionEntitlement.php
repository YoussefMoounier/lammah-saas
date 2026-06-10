<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionEntitlement extends Model
{
    use HasUlids;

    protected $table = 'subscription_entitlements';

    protected $guarded = [];

    protected $casts = [
        'starts_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'expires_at' => 'datetime',
        'auto_renew' => 'boolean',
        'source' => 'array',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WooCustomer::class, 'customer_id');
    }
}
