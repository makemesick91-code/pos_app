<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Entitlements\EntitlementAccessService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:access-check. Dry-run (default) evaluation of a runtime
 * entitlement decision for a tenant/action/feature/export/report. Uses the
 * canonical EntitlementAccessService explain() path, which does NOT persist an
 * audit row unless --record is passed (audit-only, never a mutation). Deterministic
 * and safe (ENT-R019/R020).
 */
class EntitlementAccessCheckCommand extends Command
{
    protected $signature = 'entitlement:access-check
        {--tenant= : Tenant id or code (required)}
        {--action=branch : branch|user|cashier|device|outlet|register|feature|export|report|write|read}
        {--key= : Feature/export/report key when action is feature/export/report}
        {--record : Also persist the decision to the audit trail (audit only, no mutation)}
        {--json : Output JSON}';

    protected $description = 'Dry-run a runtime entitlement decision for a tenant/action/feature/export/report.';

    public function handle(EntitlementAccessService $access): int
    {
        $selector = (string) $this->option('tenant');
        if ($selector === '') {
            $this->error('The --tenant option is required.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()
            ->when(is_numeric($selector), fn ($q) => $q->where('id', (int) $selector))
            ->when(! is_numeric($selector), fn ($q) => $q->where('code', $selector))
            ->first();

        if (! $tenant instanceof Tenant) {
            $this->error("No tenant found for selector: {$selector}");

            return self::FAILURE;
        }

        $action = (string) $this->option('action');
        $key = (string) $this->option('key');

        // Dry-run (explain) by default; --record uses the audited path.
        if ($this->option('record')) {
            $decision = $this->recordedDecision($access, $tenant, $action, $key)->toArray();
        } else {
            $decision = $access->explain($tenant, $key, $action);
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($decision, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->line("Tenant #{$tenant->id} ({$tenant->code}) — action: {$action}".($key !== '' ? " key: {$key}" : ''));
        $this->line('  allowed: '.($decision['allowed'] ? 'yes' : 'no'));
        $this->line('  decision: '.$decision['decision']);
        $this->line('  reason_code: '.$decision['reason_code']);
        $this->line('  billing_state: '.($decision['billing_state'] ?? 'n/a'));

        return self::SUCCESS;
    }

    private function recordedDecision(EntitlementAccessService $access, Tenant $tenant, string $action, string $key)
    {
        return match ($action) {
            'branch' => $access->canCreateBranch($tenant),
            'user' => $access->canCreateUser($tenant),
            'cashier' => $access->canCreateCashier($tenant),
            'device' => $access->canRegisterDevice($tenant),
            'outlet', 'register' => $access->canCreateOutletOrRegister($tenant),
            'feature' => $access->canUseFeature($tenant, $key),
            'export' => $access->canUseExport($tenant, $key),
            'report' => $access->canUseReport($tenant, $key),
            'read' => $access->canRead($tenant, null, $key ?: null),
            default => $access->canWrite($tenant, null, $key ?: null),
        };
    }
}
