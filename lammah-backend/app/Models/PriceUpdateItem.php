<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceUpdateItem extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'old_regular_price' => 'decimal:4',
        'old_sale_price' => 'decimal:4',
        'new_regular_price' => 'decimal:4',
        'new_sale_price' => 'decimal:4',
        'rollback_payload' => 'array',
        'applied_at' => 'datetime',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(PriceUpdateBatch::class, 'batch_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WooProduct::class, 'product_id');
    }
}
