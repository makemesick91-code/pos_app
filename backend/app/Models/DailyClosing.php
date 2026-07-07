<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant/store-owned daily closing snapshot (Sprint 9). Totals are always
 * backend-computed by the report services at close time and stored immutably;
 * the `snapshot` column keeps the full ringkasan as it was at close. Only one
 * closing may exist per (tenant_id, store_id, business_date). See Sprint 9
 * evidence.
 */
class DailyClosing extends Model
{
    use HasFactory;

    public const STATUS_CLOSED = 'CLOSED';

    /**
     * Transient (non-persisted) flag set by DailyClosingService when a close
     * request replays an already-stored closing. Surfaced as
     * meta.duplicate_replay and never written to the database.
     */
    public bool $duplicateReplay = false;

    protected $fillable = [
        'tenant_id',
        'store_id',
        'business_date',
        'closed_by',
        'closed_at',
        'status',
        'sales_count',
        'cancelled_sales_count',
        'cash_total',
        'qris_total',
        'gross_total',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_total',
        'change_total',
        'inventory_sale_out_qty',
        'snapshot',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'date',
            'closed_at' => 'datetime',
            'sales_count' => 'integer',
            'cancelled_sales_count' => 'integer',
            'cash_total' => 'decimal:2',
            'qris_total' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
            'change_total' => 'decimal:2',
            'inventory_sale_out_qty' => 'decimal:2',
            'snapshot' => 'array',
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

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeForStore(Builder $query, int $storeId): Builder
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForBusinessDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('business_date', $date);
    }

    public function scopeClosed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CLOSED);
    }
}
