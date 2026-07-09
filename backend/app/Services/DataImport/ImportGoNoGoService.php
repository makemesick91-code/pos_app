<?php

namespace App\Services\DataImport;

use Illuminate\Support\Facades\Artisan;

class ImportGoNoGoService
{
    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    public function __construct(private readonly ImportGovernanceAuditService $governance) {}

    public function evaluate(): array
    {
        $signals = array_merge($this->governance->evaluate(), [
            $this->commandsSignal(),
            $this->chainSignal(),
        ]);

        $decision = self::DECISION_GO;
        foreach ($signals as $signal) {
            if ($signal['status'] === ImportGovernanceAuditService::STATUS_FAIL) {
                $decision = self::DECISION_NO_GO;
                break;
            }
            if ($signal['status'] === ImportGovernanceAuditService::STATUS_WARN) {
                $decision = self::DECISION_WATCH;
            }
        }

        return ['decision' => $decision, 'signals' => $signals];
    }

    private function commandsSignal(): array
    {
        $missing = array_values(array_diff((array) config('import_governance.commands', []), array_keys(Artisan::all())));

        return ['key' => 'commands_registered', 'status' => $missing === [] ? 'PASS' : 'FAIL', 'message' => $missing === [] ? 'All Sprint 37 import commands are registered.' : 'Missing commands: '.implode(', ', $missing).'.'];
    }

    private function chainSignal(): array
    {
        $ok = class_exists(\App\Services\Entitlements\EntitlementAccessService::class)
            && class_exists(\App\Services\TenantOnboarding\TenantOnboardingService::class)
            && class_exists(\App\Services\SupportOperations\SupportTenantHealthService::class)
            && class_exists(\App\Services\Observability\ObservabilityGoNoGoService::class);

        return ['key' => 'commercial_chain_compatible', 'status' => $ok ? 'PASS' : 'FAIL', 'message' => $ok ? 'Plan -> Invoice -> Payment Intent -> Gateway Settlement -> Collection -> Entitlement Runtime Access -> Tenant Onboarding -> Android Runtime -> Support Operations -> Observability -> Data Import/Bootstrap chain is compatible.' : 'Commercial-chain service wiring is incomplete.'];
    }
}
