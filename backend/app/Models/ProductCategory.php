<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tenant-owned product category. A null store_id means the category is global
 * for the tenant; a set store_id scopes it to one store/branch.
 */
class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
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

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
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
     * Categories visible for a store context: global (store_id null) plus the
     * store-specific ones. A null $storeId returns only global categories.
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

    public function scopeUpdatedSince(Builder $query, ?string $timestamp): Builder
    {
        if ($timestamp !== null) {
            $query->where('updated_at', '>=', $timestamp);
        }

        return $query;
    }
}
