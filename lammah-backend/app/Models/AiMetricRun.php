<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class AiMetricRun extends Model
{
    use HasUlids;

    protected $table = 'ai_metric_runs';

    protected $guarded = [];

    protected $casts = [
        'parameters' => 'array',
        'result_summary' => 'array',
        'input_window_start' => 'datetime',
        'input_window_end' => 'datetime',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
