<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDataImportArtifact extends Model
{
    protected $fillable = [
        'tenant_data_import_run_id',
        'tenant_id',
        'artifact_type',
        'storage_disk',
        'storage_path_hash',
        'file_hash',
        'metadata_json',
    ];

    protected function casts(): array
    {
        return ['metadata_json' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(TenantDataImportRun::class, 'tenant_data_import_run_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
