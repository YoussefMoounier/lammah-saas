<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShiftOrderAttribution extends Model
{
    use HasUlids;

    protected $guarded = [];

    protected $casts = [
        'gross_revenue' => 'decimal:4',
        'net_profit' => 'decimal:4',
        'attributed_at' => 'datetime',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(WooOrder::class, 'order_id');
    }
}
