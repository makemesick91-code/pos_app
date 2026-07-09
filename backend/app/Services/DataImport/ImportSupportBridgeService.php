<?php

namespace App\Services\DataImport;

use App\Models\Tenant;
use App\Models\TenantDataImportRun;

class ImportSupportBridgeService
{
    public function summaryForTenant(Tenant $tenant, int $limit = 10): array
    {
        return TenantDataImportRun::query()
            ->forTenant((int) $tenant->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (TenantDataImportRun $run) => [
                'id' => $run->id,
                'type' => $run->import_type,
                'status' => $run->status,
                'mode' => $run->mode,
                'total_rows' => $run->total_rows,
                'invalid_rows' => $run->invalid_rows,
                'failed_rows' => $run->failed_rows,
                'created_at' => $run->created_at?->toISOString(),
            ])
            ->all();
    }
}
