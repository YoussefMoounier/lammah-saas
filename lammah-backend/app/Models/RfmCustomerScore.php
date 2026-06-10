<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RfmCustomerScore extends Model
{
    use HasUlids;

    protected $table = 'rfm_customer_scores';

    protected $guarded = [];

    protected $casts = [
        'monetary_value' => 'decimal:4',
        'subscription_expires_at' => 'datetime',
        'churn_probability' => 'decimal:2',
        'calculated_at' => 'datetime',
        'signals' => 'array',
    ];
}
