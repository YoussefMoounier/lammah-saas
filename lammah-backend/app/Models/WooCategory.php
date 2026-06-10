<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WooCategory extends Model
{
    use HasUlids;

    protected $table = 'woo_categories';

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
    ];
}
