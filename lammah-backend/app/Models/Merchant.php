<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Merchant extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'merchants';

    protected $guarded = [];

    protected $casts = [
        'billing_email_encrypted' => 'encrypted',
        'phone_encrypted' => 'encrypted',
        'settings' => 'array',
    ];

    public function stores(): HasMany
    {
        return $this->hasMany(WooCommerceStore::class, 'merchant_id');
    }
}
