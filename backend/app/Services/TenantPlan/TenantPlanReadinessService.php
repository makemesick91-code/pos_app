<?php

namespace App\Services\TenantPlan;

use App\Models\PlanEntitlement;
use App\Models\PlanUsageLimit;
use App\Models\TenantPlan;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 26 — tenant plan / feature entitlement / usage-limit governance
 * readiness.
 *
 * Aggregates the automation guardrails, the plan catalogue source-of-truth
 * contract, the entitlement/usage registries, the persisted catalogue tables, the
 * runtime enforcement audit, the canonical TPE-R rules registry, and the required
 * docs into a secret-safe PASS/WARN/FAIL report and a GO/WATCH/NO_GO decision.
 *
 * NEVER charges, NEVER calls a gateway, NEVER suspends/reactivates a tenant, and
 * NEVER weakens Sprint 25 lifecycle suspension governance (TPE-R012).
 */
class TenantPlanReadinessService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    private const GUARDRAIL_FLAGS = [
        'client_side_entitlement_authoritative',
        'suspended_tenant_can_be_overridden_allowed',
        'entitlement_computed_in_controller_allowed',
        'plan_assignment_without_platform_admin_allowed',
        'override_without_reason_allowed',
        'real_billing_charge_on_plan_change_allowed',
    ];

    public function __construct(
        private readonly TenantPlanEnforcementAuditService $enforcement,
        private readonly TenantPlanRegistrar $registrar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $this->registrar->ensure();
        $enforcement = $this->enforcement->evaluate();

        $signals = [
            $this->guardrailSignal(),
            $this->planSourceSignal(),
            $this->catalogueSignal(),
            $this->rulesSignal(),
            $this->docsSignal(),
            $this->lifecycleCoexistenceSignal(),
            $this->enforcementSignal((string) $enforcement['decision']),
        ];

        return [
            'decision' => $this->decision($signals),
            'signals' => $signals,
            'enforcement_audit' => $enforcement,
        ];
    }

    private function guardrailSignal(): array
    {
        $enabled = [];
        foreach (self::GUARDRAIL_FLAGS as $flag) {
            if (config('tenant_plan.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return $enabled === []
            ? $this->signal('automation_guardrails', self::STATUS_PASS, count(self::GUARDRAIL_FLAGS).' automation guardrails disabled.')
            : $this->signal('automation_guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    private function planSourceSignal(): array
    {
        $planKeys = (array) config('tenant_plan.plan_keys', []);
        $default = (string) config('tenant_plan.default_plan', '');
        $required = ['starter', 'growth', 'professional', 'enterprise'];
        $missing = array_values(array_diff($required, $planKeys));

        if ($missing !== [] || ! in_array($default, $planKeys, true)) {
            return $this->signal('plan_source_of_truth', self::STATUS_FAIL, 'Plan source incomplete'.($missing === [] ? ' (default plan not in catalogue).' : ': missing '.implode(', ', $missing)));
        }

        return $this->signal('plan_source_of_truth', self::STATUS_PASS, count($planKeys).' plan keys defined; default plan "'.$default.'" is a restricted catalogue plan.');
    }

    private function catalogueSignal(): array
    {
        $tables = Schema::hasTable((new TenantPlan)->getTable())
            && Schema::hasTable((new PlanEntitlement)->getTable())
            && Schema::hasTable((new PlanUsageLimit)->getTable());

        if (! $tables) {
            return $this->signal('plan_catalogue_store', self::STATUS_FAIL, 'Plan catalogue tables are missing.');
        }

        $planCount = TenantPlan::query()->count();
        if ($planCount === 0) {
            return $this->signal('plan_catalogue_store', self::STATUS_FAIL, 'Plan catalogue is empty (registrar sync did not run).');
        }

        return $this->signal('plan_catalogue_store', self::STATUS_PASS, $planCount.' plans, '.PlanEntitlement::query()->count().' entitlement rows, '.PlanUsageLimit::query()->count().' usage-limit rows synced.');
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('tenant_plan.rules', []);
        $expected = ['TPE-R001', 'TPE-R002', 'TPE-R003', 'TPE-R004', 'TPE-R005', 'TPE-R006', 'TPE-R007', 'TPE-R008', 'TPE-R009', 'TPE-R010', 'TPE-R011', 'TPE-R012'];
        $missing = array_values(array_diff($expected, array_keys($rules)));

        return $missing === []
            ? $this->signal('tenant_plan_rules', self::STATUS_PASS, count($rules).' TPE-R rules locked.')
            : $this->signal('tenant_plan_rules', self::STATUS_FAIL, 'Missing TPE-R rules: '.implode(', ', $missing));
    }

    private function docsSignal(): array
    {
        $required = (array) config('tenant_plan.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('tenant_plan_docs', self::STATUS_PASS, count($required).' tenant plan docs present.')
            : $this->signal('tenant_plan_docs', self::STATUS_FAIL, 'Missing tenant plan docs: '.implode(', ', $missing));
    }

    private function lifecycleCoexistenceSignal(): array
    {
        // TPE-R012 — Sprint 25 lifecycle rules must remain intact and suspended is
        // still a blocked lifecycle status.
        $tlsRules = (array) config('tenant_lifecycle.rules', []);
        $blocked = (array) config('tenant_lifecycle.blocked_statuses', []);
        $expected = ['TLS-R001', 'TLS-R002', 'TLS-R003', 'TLS-R004', 'TLS-R005', 'TLS-R006', 'TLS-R007', 'TLS-R008', 'TLS-R009', 'TLS-R010'];
        $missing = array_values(array_diff($expected, array_keys($tlsRules)));

        if ($missing !== [] || ! in_array('suspended', $blocked, true)) {
            return $this->signal('lifecycle_coexistence', self::STATUS_FAIL, 'Sprint 25 lifecycle governance weakened'.($missing === [] ? ' (suspended not blocked).' : ': missing '.implode(', ', $missing)));
        }

        return $this->signal('lifecycle_coexistence', self::STATUS_PASS, 'Sprint 25 TLS-R rules intact; suspended remains a blocked lifecycle status.');
    }

    private function enforcementSignal(string $decision): array
    {
        return match ($decision) {
            self::DECISION_NO_GO => $this->signal('runtime_enforcement', self::STATUS_FAIL, 'Runtime enforcement audit is NO_GO.'),
            self::DECISION_WATCH => $this->signal('runtime_enforcement', self::STATUS_WARN, 'Runtime enforcement audit is WATCH.'),
            default => $this->signal('runtime_enforcement', self::STATUS_PASS, 'Runtime enforcement audit is GO.'),
        };
    }

    /**
     * @param  array<int, array{status:string}>  $signals
     */
    private function decision(array $signals): string
    {
        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return self::DECISION_NO_GO;
            }
        }

        foreach ($signals as $signal) {
            if ($signal['status'] === self::STATUS_WARN) {
                return self::DECISION_WATCH;
            }
        }

        return self::DECISION_GO;
    }

    /** @return array{key:string,status:string,message:string} */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }

    private function repoRoot(): string
    {
        return (string) (realpath(base_path('..')) ?: base_path('..'));
    }
}
