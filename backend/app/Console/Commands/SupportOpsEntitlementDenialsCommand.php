<?php

namespace App\Console\Commands;

use App\Services\SupportOperations\SupportEntitlementViewerService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:entitlement-denials. Blocked/denied action explorer
 * sourced from the Sprint 32 decision ledger (SUP-R010/R021). No bypass, no
 * PII/secrets.
 */
class SupportOpsEntitlementDenialsCommand extends Command
{
    protected $signature = 'support-ops:entitlement-denials {--tenant= : Tenant id or code} {--limit=50} {--json}';

    protected $description = 'Show the blocked/denied entitlement action explorer for a tenant.';

    public function handle(SupportEntitlementViewerService $entitlement): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));
        if ($tenant === null) {
            $this->line('No tenant specified (or not found); pass --tenant=<id|code>.');

            return self::SUCCESS;
        }

        $data = $entitlement->summary($tenant->id, (int) $this->option('limit'));
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Entitlement decisions for tenant #'.$tenant->id.' — by_decision: '.json_encode($data['by_decision']));
        foreach ($data['denied'] as $d) {
            $this->line(sprintf('  DENIED %s/%s (%s) — %s', $d['resource_type'], $d['action'], $d['decision'], $d['reason_code']));
        }

        return self::SUCCESS;
    }
}
