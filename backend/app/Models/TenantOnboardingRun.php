<?php

namespace App\Models;

use Database\Factories\TenantOnboardingRunFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * A platform-admin-driven tenant onboarding run (Sprint 12). Tracks the tenant/
 * store/owner/subscription created by an onboarding request plus a
 * backend-generated checklist. `onboarding_reference` is unique so a replayed
 * request returns the existing run rather than creating duplicate records.
 *
 * `metadata` holds a backend-owned demo manifest (seeded ids) used by the
 * guarded demo-data reset — passwords and plain secrets are never stored here.
 */
class TenantOnboardingRun extends Model
{
    /** @use HasFactory<TenantOnboardingRunFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_RUNNING = 'RUNNING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_FAILED = 'FAILED';

    protected $fillable = [
        'onboarding_reference',
        'requested_by',
        'tenant_id',
        'default_store_id',
        'owner_user_id',
        'subscription_plan_id',
        'tenant_subscription_id',
        'status',
        'tenant_name',
        'store_name',
        'owner_name',
        'owner_email',
        'demo_data_enabled',
        'demo_data_seeded_at',
        'demo_data_reset_at',
        'checklist',
        'metadata',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'demo_data_enabled' => 'boolean',
            'demo_data_seeded_at' => 'datetime',
            'demo_data_reset_at' => 'datetime',
            'checklist' => 'array',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function defaultStore(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'default_store_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function tenantSubscription(): BelongsTo
    {
        return $this->belongsTo(TenantSubscription::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeWithStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function markRunning(): void
    {
        $this->status = self::STATUS_RUNNING;
        $this->started_at = Carbon::now();
        $this->save();
    }

    /**
     * @param  array<string, bool>  $checklist
     */
    public function markCompleted(array $checklist = []): void
    {
        $this->status = self::STATUS_COMPLETED;
        if ($checklist !== []) {
            $this->checklist = $checklist;
        }
        $this->completed_at = Carbon::now();
        $this->error_message = null;
        $this->save();
    }

    public function markFailed(Throwable|string $error): void
    {
        $this->status = self::STATUS_FAILED;
        $this->error_message = $error instanceof Throwable ? $error->getMessage() : $error;
        $this->completed_at = Carbon::now();
        $this->save();
    }

    /**
     * The backend-owned demo manifest recorded for this run: the ids seeded so
     * the guarded reset can delete exactly what onboarding created.
     *
     * @return array<string, array<int, int>>
     */
    public function demoManifest(): array
    {
        $metadata = $this->metadata ?? [];

        return $metadata['demo_manifest'] ?? [];
    }
}
