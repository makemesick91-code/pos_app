<?php

namespace App\Console\Commands;

use App\Services\Entitlements\EntitlementGovernanceAuditService;
use Illuminate\Console\Command;

/**
 * Sprint 32 — entitlement:governance-audit. Verifies the entitlement governance
 * config, rules (ENT-R001..R024), hard guardrails, feature-key registry, and
 * runtime middleware wiring. Returns non-zero on any violation. Never prints
 * secrets, never mutates state.
 */
class EntitlementGovernanceAuditCommand extends Command
{
    protected $signature = 'entitlement:governance-audit {--json : Output JSON}';

    protected $description = 'Audit the entitlement governance config, rules, guardrails, and runtime wiring.';

    public function handle(EntitlementGovernanceAuditService $service): int
    {
        $signals = $service->evaluate();
        $passes = $service->passes();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'passes' => $passes,
                'signals' => $signals,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $passes ? self::SUCCESS : self::FAILURE;
        }

        $this->line('Entitlement Governance Audit');
        foreach ($signals as $signal) {
            $this->line("[{$signal['status']}] {$signal['key']} — {$signal['message']}");
        }
        $this->line('Result: '.($passes ? 'PASS' : 'FAIL'));

        return $passes ? self::SUCCESS : self::FAILURE;
    }
}
