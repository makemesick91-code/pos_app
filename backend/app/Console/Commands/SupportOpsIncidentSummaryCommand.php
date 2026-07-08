<?php

namespace App\Console\Commands;

use App\Models\TenantSupportIncident;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:incident-summary. Shows incidents grouped by status /
 * severity / category (SUP-R025). Optional --tenant scoping. No PII/secrets — only
 * counts and safe enums.
 */
class SupportOpsIncidentSummaryCommand extends Command
{
    protected $signature = 'support-ops:incident-summary {--tenant= : Tenant id or code} {--json}';

    protected $description = 'Summarise support incidents by status/severity/category.';

    public function handle(): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));

        $query = TenantSupportIncident::query();
        if ($tenant !== null) {
            $query->forTenant($tenant->id);
        }
        $incidents = $query->get();

        $group = function (string $field) use ($incidents): array {
            $out = [];
            foreach ($incidents as $i) {
                $out[$i->{$field}] = ($out[$i->{$field}] ?? 0) + 1;
            }

            return $out;
        };

        $data = [
            'tenant_id' => $tenant?->id,
            'total' => $incidents->count(),
            'by_status' => $group('status'),
            'by_severity' => $group('severity'),
            'by_category' => $group('category'),
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Incident summary (total '.$data['total'].')');
        $this->line('  by_status: '.json_encode($data['by_status']));
        $this->line('  by_severity: '.json_encode($data['by_severity']));
        $this->line('  by_category: '.json_encode($data['by_category']));

        return self::SUCCESS;
    }
}
