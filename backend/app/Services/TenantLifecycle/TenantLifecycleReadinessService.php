<?php

namespace App\Services\TenantLifecycle;

use App\Models\TenantLifecycleEvent;
use App\Models\TenantManualSuspension;
use Illuminate\Support\Facades\Schema;

/**
 * Sprint 25 — tenant lifecycle enforcement & manual suspension readiness.
 *
 * Aggregates the automation guardrails, the lifecycle status source-of-truth
 * contract, the manual suspension model/table availability, the runtime
 * enforcement audit, and the canonical TLS-R rules registry into a secret-safe
 * PASS/WARN/FAIL report and a GO/WATCH/NO_GO decision.
 *
 * This service NEVER suspends/reactivates a tenant, NEVER charges, NEVER calls a
 * gateway, and NEVER sends a real message.
 */
class TenantLifecycleReadinessService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    public const DECISION_GO = 'GO';
    public const DECISION_WATCH = 'WATCH';
    public const DECISION_NO_GO = 'NO_GO';

    /** Config flags that must all be false; any true value forces NO_GO. */
    private const GUARDRAIL_FLAGS = [
        'real_tenant_hard_delete_allowed',
        'auto_tenant_suspension_allowed',
        'auto_tenant_reactivation_allowed',
        'dunning_can_override_manual_suspension_allowed',
        'renewal_can_override_manual_suspension_allowed',
        'client_side_enforcement_authoritative',
        'public_tenant_suspension_api_allowed',
        'tenant_status_computed_in_controller_allowed',
        'real_notification_sending_allowed',
    ];

    public function __construct(
        private readonly TenantLifecycleEnforcementAuditService $enforcement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function evaluate(): array
    {
        $enforcement = $this->enforcement->evaluate();

        $signals = [
            $this->guardrailSignal(),
            $this->statusSourceSignal(),
            $this->suspensionModelSignal(),
            $this->docsSignal(),
            $this->rulesSignal(),
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
            if (config('tenant_lifecycle.'.$flag) === true) {
                $enabled[] = $flag;
            }
        }

        return $enabled === []
            ? $this->signal('automation_guardrails', self::STATUS_PASS, count(self::GUARDRAIL_FLAGS).' automation guardrails disabled.')
            : $this->signal('automation_guardrails', self::STATUS_FAIL, 'Enabled guardrail(s): '.implode(', ', $enabled));
    }

    private function statusSourceSignal(): array
    {
        $statuses = (array) config('tenant_lifecycle.statuses', []);
        $blocked = (array) config('tenant_lifecycle.blocked_statuses', []);
        $required = ['onboarding', 'active', 'grace', 'past_due', 'suspended', 'cancelled', 'archived'];
        $missing = array_values(array_diff($required, $statuses));
        $blockedOk = in_array('suspended', $blocked, true);

        if ($missing !== [] || ! $blockedOk) {
            return $this->signal('lifecycle_status_source', self::STATUS_FAIL, 'Lifecycle status source incomplete'.($missing === [] ? ' (suspended not blocked).' : ': missing '.implode(', ', $missing)));
        }

        return $this->signal('lifecycle_status_source', self::STATUS_PASS, count($statuses).' lifecycle statuses defined; suspended is blocked.');
    }

    private function suspensionModelSignal(): array
    {
        $tables = Schema::hasTable((new TenantManualSuspension)->getTable())
            && Schema::hasTable((new TenantLifecycleEvent)->getTable());

        return $tables
            ? $this->signal('manual_suspension_store', self::STATUS_PASS, 'Manual suspension + lifecycle event tables present.')
            : $this->signal('manual_suspension_store', self::STATUS_FAIL, 'Manual suspension / lifecycle event tables are missing.');
    }

    private function docsSignal(): array
    {
        $required = (array) config('tenant_lifecycle.required_docs', []);
        $missing = [];
        foreach ($required as $doc) {
            if (! is_file($this->repoRoot().'/'.ltrim((string) $doc, '/'))) {
                $missing[] = $doc;
            }
        }

        return $missing === []
            ? $this->signal('tenant_lifecycle_docs', self::STATUS_PASS, count($required).' tenant lifecycle docs present.')
            : $this->signal('tenant_lifecycle_docs', self::STATUS_FAIL, 'Missing tenant lifecycle docs: '.implode(', ', $missing));
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('tenant_lifecycle.rules', []);
        $expected = ['TLS-R001', 'TLS-R002', 'TLS-R003', 'TLS-R004', 'TLS-R005', 'TLS-R006', 'TLS-R007', 'TLS-R008', 'TLS-R009', 'TLS-R010'];
        $missing = array_values(array_diff($expected, array_keys($rules)));

        return $missing === []
            ? $this->signal('tenant_lifecycle_rules', self::STATUS_PASS, count($rules).' TLS-R rules locked.')
            : $this->signal('tenant_lifecycle_rules', self::STATUS_FAIL, 'Missing TLS-R rules: '.implode(', ', $missing));
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
