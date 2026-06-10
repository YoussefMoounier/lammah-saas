<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class SyncRun extends Model
{
    use HasUlids;

    protected $table = 'sync_runs';

    protected $guarded = [];

    protected $casts = [
        'totals' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
