<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class FraudRiskSignal extends Model
{
    use HasUlids;

    protected $table = 'fraud_risk_signals';

    protected $guarded = [];

    protected $casts = [
        'risk_score' => 'decimal:2',
        'evidence' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];
}
