<?php

namespace App\Services\DataImport;

use App\Models\TenantDataImportRun;

class ImportObservabilityBridgeService
{
    public function signals(): array
    {
        $stuckMinutes = (int) config('import_governance.observability.stuck_import_minutes', 30);
        $failed = TenantDataImportRun::query()->whereIn('status', ['failed', 'partial_failed'])->count();
        $stuck = TenantDataImportRun::query()
            ->whereIn('status', ['validating', 'executing'])
            ->where('updated_at', '<', now()->subMinutes($stuckMinutes))
            ->count();

        return [
            ['key' => 'failed_imports', 'status' => $failed > 0 ? 'WARN' : 'PASS', 'count' => $failed],
            ['key' => 'stuck_imports', 'status' => $stuck > 0 ? 'WARN' : 'PASS', 'count' => $stuck],
        ];
    }
}
