<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sprint 33 — one provisioning step trace/audit record for an onboarding run.
 * The row records what was created (subject_type/subject_id), a redacted
 * metadata blob and a redacted failure reason. Never stores secrets/PII.
 */
class TenantProvisioningStep extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tenant_provisioning_run_id',
        'tenant_id',
        'step_key',
        'status',
        'subject_type',
        'subject_id',
        'idempotency_key',
        'reason_code',
        'failure_reason',
        'metadata_json',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TenantProvisioningRun::class, 'tenant_provisioning_run_id');
    }
}
