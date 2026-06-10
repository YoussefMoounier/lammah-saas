<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FixedMonthlyCost extends Model
{
    use HasUlids;

    protected $table = 'fixed_monthly_costs';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:4',
        'starts_on' => 'date',
        'ends_on' => 'date',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
