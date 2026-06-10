<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WooCustomer extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'woo_customers';

    protected $guarded = [];

    protected $casts = [
        'email_encrypted' => 'encrypted',
        'phone_encrypted' => 'encrypted',
        'ip_address_encrypted' => 'encrypted',
        'first_name_encrypted' => 'encrypted',
        'last_name_encrypted' => 'encrypted',
        'raw_payload' => 'array',
        'woo_created_at' => 'datetime',
        'woo_updated_at' => 'datetime',
        'synced_at' => 'datetime',
        'total_spent' => 'decimal:4',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(WooOrder::class, 'customer_id');
    }
}
