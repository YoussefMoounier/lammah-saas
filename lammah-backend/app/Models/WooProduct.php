<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WooProduct extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'woo_products';

    protected $guarded = [];

    protected $casts = [
        'attributes' => 'array',
        'raw_payload' => 'array',
        'regular_price' => 'decimal:4',
        'sale_price' => 'decimal:4',
        'current_price' => 'decimal:4',
        'manages_stock' => 'boolean',
        'is_virtual' => 'boolean',
        'is_downloadable' => 'boolean',
        'synced_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(WooCategory::class, 'woo_product_category', 'product_id', 'category_id');
    }
}
