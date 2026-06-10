<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WooOrder extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'woo_orders';

    protected $guarded = [];

    protected $casts = [
        'customer_email_encrypted' => 'encrypted',
        'customer_phone_encrypted' => 'encrypted',
        'customer_ip_encrypted' => 'encrypted',
        'billing_snapshot' => 'array',
        'shipping_snapshot' => 'array',
        'raw_payload' => 'array',
        'subtotal' => 'decimal:4',
        'discount_total' => 'decimal:4',
        'shipping_total' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'fee_total' => 'decimal:4',
        'refund_total' => 'decimal:4',
        'total' => 'decimal:4',
        'woo_created_at' => 'datetime',
        'paid_at' => 'datetime',
        'completed_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(WooOrderItem::class, 'order_id');
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(WooCommerceStore::class, 'store_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WooCustomer::class, 'customer_id');
    }
}
