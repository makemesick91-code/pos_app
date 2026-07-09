<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDataImportRow extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_VALID = 'valid';
    public const STATUS_INVALID = 'invalid';
    public const STATUS_CREATED = 'created';
    public const STATUS_UPDATED = 'updated';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    protected $fillable = [
        'tenant_data_import_run_id',
        'tenant_id',
        'row_number',
        'row_type',
        'row_fingerprint',
        'status',
        'action',
        'subject_type',
        'subject_id',
        'error_code',
        'error_message_safe',
        'original_row_hash',
        'normalized_json',
        'metadata_json',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'normalized_json' => 'array',
            'metadata_json' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TenantDataImportRun::class, 'tenant_data_import_run_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }
}
