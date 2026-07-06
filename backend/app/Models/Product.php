<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned product. A null store_id means the product is global for the
 * tenant; a set store_id scopes it to one store/branch. sku is unique per
 * tenant. Store-specific price overrides live in ProductStorePrice.
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'category_id',
        'sku',
        'barcode',
        'name',
        'unit',
        'cost_price',
        'selling_price',
        'is_stock_tracked',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'is_stock_tracked' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function storePrices(): HasMany
    {
        return $this->hasMany(ProductStorePrice::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Products visible for a store context: global (store_id null) plus the
     * store-specific ones. A null $storeId returns only global products.
     */
    public function scopeForStoreContext(Builder $query, ?int $storeId): Builder
    {
        return $query->where(function (Builder $q) use ($storeId) {
            $q->whereNull('store_id');
            if ($storeId !== null) {
                $q->orWhere('store_id', $storeId);
            }
        });
    }

    public function scopeGlobalForTenant(Builder $query): Builder
    {
        return $query->whereNull('store_id');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if ($term !== null && $term !== '') {
            $query->where(function (Builder $q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%");
            });
        }

        return $query;
    }

    public function scopeUpdatedSince(Builder $query, ?string $timestamp): Builder
    {
        if ($timestamp !== null) {
            $query->where('updated_at', '>=', $timestamp);
        }

        return $query;
    }
}
