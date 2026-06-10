<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceUpdateBatch extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'value' => 'decimal:4',
        'target_filters' => 'array',
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function store(): BelongsTo
    {
        return $this->belongsTo(WooCommerceStore::class, 'store_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PriceUpdateItem::class, 'batch_id');
    }
}
