<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant-owned, store-scoped price override for a product. Exactly one active
 * override per (tenant_id, store_id, product_id). Drives effective_selling_price
 * in the Android product sync payload.
 */
class ProductStorePrice extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'product_id',
        'selling_price',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForStoreContext(Builder $query, ?int $storeId): Builder
    {
        if ($storeId !== null) {
            $query->where('store_id', $storeId);
        }

        return $query;
    }
}
