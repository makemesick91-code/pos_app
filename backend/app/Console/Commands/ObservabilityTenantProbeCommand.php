<?php

namespace App\Console\Commands;

use App\Services\Observability\TenantRuntimeProbeService;
use Illuminate\Console\Command;

/**
 * Sprint 36 — observability:tenant-probe. Shows a safe, tenant-isolated runtime
 * health probe (reuses the Sprint 35 health computation). --tenant selects one
 * tenant (id or code); otherwise a brief list is printed. No PII/secrets.
 */
class ObservabilityTenantProbeCommand extends Command
{
    protected $signature = 'observability:tenant-probe {--tenant= : Tenant id or code} {--json : Output JSON}';

    protected $description = 'Show a safe tenant runtime health probe.';

    public function handle(TenantRuntimeProbeService $probe): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));

        $data = $tenant !== null ? $probe->probe($tenant) : ['tenants' => $probe->probeMany()];

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($tenant === null) {
            $this->line('Tenant runtime probes (brief):');
            foreach ($data['tenants'] as $row) {
                $this->line("  [{$row['health_status']}] tenant#{$row['tenant_id']} ({$row['tenant_code']})");
            }

            return self::SUCCESS;
        }

        $this->line("Tenant#{$data['tenant_id']} ({$data['tenant_code']}) health: {$data['health_status']}");
        $this->line('Reasons: '.implode(', ', $data['reason_codes']));

        return self::SUCCESS;
    }
}
