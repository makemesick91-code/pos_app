<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A SaaS billing invoice line (Sprint 23). line_total is calculated server-side;
 * no external billing provider is ever called. No secrets stored.
 */
class SaasBillingInvoiceLine extends Model
{
    public const TYPE_SUBSCRIPTION = 'SUBSCRIPTION';
    public const TYPE_DEVICE = 'DEVICE';
    public const TYPE_SETUP = 'SETUP';
    public const TYPE_SUPPORT = 'SUPPORT';
    public const TYPE_ADJUSTMENT = 'ADJUSTMENT';
    public const TYPE_DISCOUNT = 'DISCOUNT';
    public const TYPE_OTHER = 'OTHER';

    /** @var array<int,string> */
    public const ITEM_TYPES = [
        self::TYPE_SUBSCRIPTION,
        self::TYPE_DEVICE,
        self::TYPE_SETUP,
        self::TYPE_SUPPORT,
        self::TYPE_ADJUSTMENT,
        self::TYPE_DISCOUNT,
        self::TYPE_OTHER,
    ];

    protected $fillable = [
        'invoice_id',
        'line_reference',
        'item_type',
        'description',
        'quantity',
        'unit_amount',
        'discount_amount',
        'tax_amount',
        'line_total',
        'source_type',
        'source_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(SaasBillingInvoice::class, 'invoice_id');
    }
}
