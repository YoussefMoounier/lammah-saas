<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class WooWebhookEvent extends Model
{
    use HasUlids;

    protected $table = 'woo_webhook_events';

    protected $guarded = [];

    protected $casts = [
        'signature_valid' => 'boolean',
        'headers' => 'array',
        'payload' => 'array',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
    ];
}
