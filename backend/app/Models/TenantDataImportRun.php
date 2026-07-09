<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TenantDataImportRun extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_EXECUTING = 'executing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL_FAILED = 'partial_failed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';
    public const STATUS_CANCELLED = 'cancelled';

    public const MODE_DRY_RUN = 'dry_run';
    public const MODE_EXECUTE = 'execute';

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'provisioning_run_id',
        'requested_by_user_id',
        'import_type',
        'source_format',
        'status',
        'mode',
        'idempotency_key',
        'original_filename_hash',
        'file_hash',
        'total_rows',
        'valid_rows',
        'invalid_rows',
        'created_rows',
        'updated_rows',
        'skipped_rows',
        'failed_rows',
        'rollback_supported',
        'rolled_back_at',
        'failure_reason',
        'summary_json',
        'metadata_json',
        'started_at',
        'completed_at',
        'failed_at',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'metadata_json' => 'array',
            'rollback_supported' => 'boolean',
            'rolled_back_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'branch_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function rows(): HasMany
    {
        return $this->hasMany(TenantDataImportRow::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
