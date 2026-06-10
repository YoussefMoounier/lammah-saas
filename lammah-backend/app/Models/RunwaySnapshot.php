<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class RunwaySnapshot extends Model
{
    use HasUlids;

    protected $table = 'runway_snapshots';

    protected $guarded = [];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'net_profit' => 'decimal:4',
        'fixed_cost_total' => 'decimal:4',
        'cash_balance' => 'decimal:4',
        'burn_rate' => 'decimal:4',
        'runway_months' => 'decimal:2',
        'calculated_at' => 'datetime',
        'payload' => 'array',
    ];
}
