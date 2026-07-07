<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single ledger entry in the tenant-owned, store-scoped inventory ledger.
 * Current stock is the signed sum of these rows for a product — this table is
 * the sole source of truth (Sprint 8). `signed_qty` is always backend-computed.
 */
class InventoryMovement extends Model
{
    use HasFactory;

    public const TYPE_OPENING = 'OPENING';
    public const TYPE_SALE_OUT = 'SALE_OUT';
    public const TYPE_ADJUSTMENT_IN = 'ADJUSTMENT_IN';
    public const TYPE_ADJUSTMENT_OUT = 'ADJUSTMENT_OUT';
    public const TYPE_RETURN_IN = 'RETURN_IN';

    public const SOURCE_API = 'API';
    public const SOURCE_SALE = 'SALE';
    public const SOURCE_ADJUSTMENT = 'ADJUSTMENT';
    public const SOURCE_OPENING = 'OPENING';

    public const REFERENCE_SALE_ITEM = 'sale_item';

    /** Movement types that increase stock (positive signed_qty). */
    public const POSITIVE_TYPES = [
        self::TYPE_OPENING,
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_RETURN_IN,
    ];

    /** Movement types that decrease stock (negative signed_qty). */
    public const NEGATIVE_TYPES = [
        self::TYPE_SALE_OUT,
        self::TYPE_ADJUSTMENT_OUT,
    ];

    /** Movement types a client may create through the adjustment endpoint. */
    public const ADJUSTMENT_TYPES = [
        self::TYPE_OPENING,
        self::TYPE_ADJUSTMENT_IN,
        self::TYPE_ADJUSTMENT_OUT,
    ];

    protected $fillable = [
        'tenant_id',
        'store_id',
        'product_id',
        'movement_type',
        'qty',
        'signed_qty',
        'reference_type',
        'reference_id',
        'source',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:2',
            'signed_qty' => 'decimal:2',
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForProduct(Builder $query, int $productId): Builder
    {
        return $query->where('product_id', $productId);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('movement_type', $type);
    }

    /**
     * The sign a movement type applies to stock: +1, -1, or 0 if unknown.
     */
    public static function signFor(string $type): int
    {
        if (in_array($type, self::POSITIVE_TYPES, true)) {
            return 1;
        }

        if (in_array($type, self::NEGATIVE_TYPES, true)) {
            return -1;
        }

        return 0;
    }
}
