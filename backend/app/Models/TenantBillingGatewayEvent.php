<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 31 — a verified gateway/webhook event, stored idempotently. The raw
 * signature/secret is never persisted (only a truncated fingerprint). A
 * failed/cancelled/expired event updates state but never marks an invoice paid
 * (PGW-R009). See config/payment_gateway_governance.php.
 */
class TenantBillingGatewayEvent extends Model
{
    use HasFactory;

    public const STATUS_RECEIVED = 'received';

    public const STATUS_VERIFIED = 'verified';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_PROCESSED = 'processed';

    public const STATUS_IGNORED = 'ignored';

    public const STATUS_REPLAYED = 'replayed';

    protected $fillable = [
        'provider',
        'event_type',
        'provider_event_id',
        'provider_reference',
        'payment_intent_id',
        'invoice_id',
        'payload_hash',
        'signature_hash',
        'signature_verified',
        'status',
        'normalized_status',
        'amount',
        'currency',
        'occurred_at',
        'processed_at',
        'failure_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'signature_verified' => 'boolean',
            'amount' => 'integer',
            'occurred_at' => 'datetime',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(TenantBillingPaymentIntent::class, 'payment_intent_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(TenantBillingInvoice::class, 'invoice_id');
    }
}
