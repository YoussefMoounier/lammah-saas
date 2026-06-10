<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class OrderProfitSnapshot extends Model
{
    use HasUlids;

    protected $table = 'order_profit_snapshots';

    protected $guarded = [];

    protected $casts = [
        'gross_revenue' => 'decimal:4',
        'product_cost_total' => 'decimal:4',
        'gateway_fee_total' => 'decimal:4',
        'shipping_cost_total' => 'decimal:4',
        'refunds_total' => 'decimal:4',
        'tax_total' => 'decimal:4',
        'net_profit' => 'decimal:4',
        'margin_percent' => 'decimal:4',
        'calculated_at' => 'datetime',
        'calculation_payload' => 'array',
    ];
}
