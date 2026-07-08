<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\SupportOperations\SupportBillingViewerService;
use Illuminate\Console\Command;

/**
 * Sprint 35 — support-ops:billing-status. Read-only invoice/collection status
 * (SUP-R008). No mutation, never marks an invoice paid. No PII/secrets.
 */
class SupportOpsBillingStatusCommand extends Command
{
    protected $signature = 'support-ops:billing-status {--tenant= : Tenant id or code} {--json}';

    protected $description = 'Show read-only billing/invoice/collection status for a tenant.';

    public function handle(SupportBillingViewerService $billing): int
    {
        $tenant = SupportTenantResolver::resolve($this->option('tenant'));
        if ($tenant === null) {
            $this->line('No tenant specified (or not found); pass --tenant=<id|code>.');

            return self::SUCCESS;
        }

        $data = $billing->summary($tenant->id);
        if ($this->option('json')) {
            $this->line((string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line('Billing status for tenant #'.$tenant->id);
        $this->line('  invoices: '.$data['invoice_count'].' outstanding: '.$data['outstanding_amount']);
        $this->line('  by_collection_state: '.json_encode($data['by_collection_state']));

        return self::SUCCESS;
    }
}
