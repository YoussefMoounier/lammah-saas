<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WooOrderItem extends Model
{
    use HasUlids;

    protected $table = 'woo_order_items';

    protected $guarded = [];

    protected $casts = [
        'metadata' => 'array',
        'raw_payload' => 'array',
        'quantity' => 'decimal:4',
        'subtotal' => 'decimal:4',
        'total' => 'decimal:4',
        'tax_total' => 'decimal:4',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class, 'order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(WooProduct::class, 'product_id');
    }
}
