<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Sprint 33 — the onboarding lifecycle record for one tenant provisioning
 * attempt. Idempotent by `idempotency_key`. Never stores secrets/PII: the
 * checklist/metadata/failure columns are redacted upstream.
 */
class TenantProvisioningRun extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROVISIONING = 'provisioning';
    public const STATUS_TRIAL_ACTIVE = 'trial_active';
    public const STATUS_WAITING_PAYMENT = 'waiting_payment';
    public const STATUS_PAID_ACTIVE = 'paid_active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_PLATFORM_ADMIN = 'platform_admin';
    public const TYPE_APPROVED_SIGNUP = 'approved_signup';
    public const TYPE_IMPORT_SEED = 'import_seed';
    public const TYPE_INTERNAL = 'internal';

    protected $fillable = [
        'tenant_id',
        'requested_plan_code',
        'resolved_plan_code',
        'onboarding_type',
        'status',
        'idempotency_key',
        'requested_by_user_id',
        'owner_user_id',
        'first_branch_id',
        'first_cashier_user_id',
        'first_register_id',
        'first_device_id',
        'trial_starts_at',
        'trial_ends_at',
        'billing_period',
        'tenant_billing_invoice_id',
        'payment_intent_id',
        'checklist_json',
        'failure_reason',
        'metadata_json',
        'started_at',
        'completed_at',
        'failed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'trial_starts_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'checklist_json' => 'array',
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(TenantProvisioningStep::class);
    }

    /**
     * Statuses from which a run may still be cancelled safely (ONB-R019/R020).
     */
    public function isCancellable(): bool
    {
        return in_array($this->status, [
            self::STATUS_DRAFT,
            self::STATUS_PENDING,
            self::STATUS_PROVISIONING,
            self::STATUS_FAILED,
        ], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function markFailed(string $reason): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failure_reason = $reason;
        $this->failed_at = Carbon::now();
        $this->save();
    }
}
