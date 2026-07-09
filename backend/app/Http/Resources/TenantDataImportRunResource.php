<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantDataImportRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'branch_id' => $this->branch_id,
            'provisioning_run_id' => $this->provisioning_run_id,
            'import_type' => $this->import_type,
            'source_format' => $this->source_format,
            'status' => $this->status,
            'mode' => $this->mode,
            'total_rows' => $this->total_rows,
            'valid_rows' => $this->valid_rows,
            'invalid_rows' => $this->invalid_rows,
            'created_rows' => $this->created_rows,
            'updated_rows' => $this->updated_rows,
            'skipped_rows' => $this->skipped_rows,
            'failed_rows' => $this->failed_rows,
            'rollback_supported' => (bool) $this->rollback_supported,
            'failure_reason' => $this->failure_reason,
            'summary' => $this->summary_json,
            'created_at' => $this->created_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'failed_at' => $this->failed_at?->toISOString(),
            'rolled_back_at' => $this->rolled_back_at?->toISOString(),
        ];
    }
}
