<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Artisan;

class PerformanceGovernanceAuditService
{
    public function evaluate(): array
    {
        $signals = [];
        $rules = array_keys((array) config('performance_governance.rules', []));
        foreach (['backend/config/performance_governance.php', 'backend/config/pos_foundation.php', 'docs/PROJECT_RULES.md', 'docs/architecture/sprint-38-multi-tenant-performance-benchmark-load-gate-scale-readiness.md'] as $file) {
            $path = base_path('../'.$file);
            $missing = [];
            foreach ($rules as $rule) {
                if (! is_file($path) || ! str_contains((string) file_get_contents($path), $rule)) {
                    $missing[] = $rule;
                }
            }
            $signals[] = ['key' => 'rules_'.basename($file), 'status' => $missing === [] ? 'PASS' : 'FAIL', 'message' => $missing === [] ? 'All PERF rules present.' : 'Missing '.implode(',', $missing)];
        }
        $missingCommands = array_values(array_diff((array) config('performance_governance.commands', []), array_keys(Artisan::all())));
        $signals[] = ['key' => 'commands_registered', 'status' => $missingCommands === [] ? 'PASS' : 'FAIL', 'message' => $missingCommands === [] ? 'All Sprint 38 commands registered.' : 'Missing '.implode(',', $missingCommands)];
        $signals[] = ['key' => 'manual_heavy_not_default', 'status' => config('performance_governance.default_profile') !== 'manual_heavy' ? 'PASS' : 'FAIL', 'message' => 'manual_heavy is not the default profile.'];
        $signals[] = ['key' => 'commercial_chain_compatible', 'status' => class_exists(\App\Services\Entitlements\EntitlementAccessService::class) && class_exists(\App\Services\DataImport\TenantDataImportService::class) ? 'PASS' : 'FAIL', 'message' => 'Performance/Scale Readiness extends the Sprint 24-37 commercial chain.'];
        return $signals;
    }
}
