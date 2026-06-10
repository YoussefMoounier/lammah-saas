<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ProductCost extends Model
{
    use HasUlids;

    protected $table = 'product_costs';

    protected $guarded = [];

    protected $casts = [
        'cost_amount' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];
}
