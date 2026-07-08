<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Entitlements\EntitlementSummaryService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:usage-summary. Prints a tenant's current usage vs plan
 * limits and its billing access state. Safe/redacted; no PII, no secrets
 * (ENT-R020). Filter by tenant id or code with --tenant.
 */
class EntitlementUsageSummaryCommand extends Command
{
    protected $signature = 'entitlement:usage-summary
        {--tenant= : Tenant id or code}
        {--json : Output JSON}';

    protected $description = 'Show a tenant usage-vs-limit and billing-access summary (safe, no PII).';

    public function handle(EntitlementSummaryService $summary): int
    {
        $selector = (string) $this->option('tenant');
        $tenants = $this->resolveTenants($selector);

        // An explicit selector that matches nothing is an error; an empty tenant
        // set with no selector is a valid (empty) summary, not a failure.
        if ($tenants->isEmpty() && $selector !== '') {
            $this->error("No tenant found for selector: {$selector}");

            return self::FAILURE;
        }

        if ($tenants->isEmpty()) {
            $this->line($this->option('json') ? '[]' : 'No tenants.');

            return self::SUCCESS;
        }

        $rows = $tenants->map(fn (Tenant $t) => $summary->tenantSummary($t))->values()->all();

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        foreach ($rows as $row) {
            $this->line("Tenant #{$row['tenant_id']} ({$row['tenant_code']}) — plan {$row['plan_code']}");
            $this->line("  billing: {$row['billing_state']} | write_allowed: ".($row['write_allowed'] ? 'yes' : 'no')." | reason: {$row['write_reason_code']}");
            foreach ($row['usage'] as $alias => $u) {
                $limit = $u['unlimited'] ? 'unlimited' : ($u['limit'] ?? 'n/a');
                $this->line("  {$alias}: {$u['current']} / {$limit}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function resolveTenants(string $selector)
    {
        if ($selector === '') {
            return Tenant::query()->orderBy('id')->limit(25)->get();
        }

        return Tenant::query()
            ->when(is_numeric($selector), fn ($q) => $q->where('id', (int) $selector))
            ->when(! is_numeric($selector), fn ($q) => $q->where('code', $selector))
            ->get();
    }
}
