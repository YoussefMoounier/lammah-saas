<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SalesForecast extends Model
{
    use HasUlids;

    protected $table = 'sales_forecasts';

    protected $guarded = [];

    protected $casts = [
        'forecast_month' => 'date',
        'gross_revenue_forecast' => 'decimal:4',
        'net_profit_forecast' => 'decimal:4',
        'confidence_low' => 'decimal:4',
        'confidence_high' => 'decimal:4',
        'model_coefficients' => 'array',
        'source_window_start' => 'date',
        'source_window_end' => 'date',
        'generated_at' => 'datetime',
    ];
}
