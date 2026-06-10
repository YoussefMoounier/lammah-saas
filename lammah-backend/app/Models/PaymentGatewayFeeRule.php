<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayFeeRule extends Model
{
    use HasUlids;

    protected $table = 'payment_gateway_fee_rules';

    protected $guarded = [];

    protected $casts = [
        'percent_fee' => 'decimal:4',
        'fixed_fee' => 'decimal:4',
        'min_order_amount' => 'decimal:4',
        'max_order_amount' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
