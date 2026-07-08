<?php

namespace App\Services\Entitlements;

use Illuminate\Support\Facades\Route;

/**
 * Sprint 32 — audits that the entitlement governance config, rules, guardrails,
 * and runtime wiring are intact (ENT-R023/R024). Returns a list of check signals;
 * any FAIL makes the go-no-go NO_GO. Never prints secrets, never mutates state.
 */
class EntitlementGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';

    public const STATUS_FAIL = 'FAIL';

    /**
     * @return array<int, array{key: string, status: string, message: string}>
     */
    public function evaluate(): array
    {
        return [
            $this->rulesSignal(),
            $this->safeDefaultsSignal(),
            $this->failClosedSignal(),
            $this->guardrailsSignal(),
            $this->featureKeysSignal(),
            $this->middlewareSignal(),
            $this->auditRequirementSignal(),
        ];
    }

    public function passes(): bool
    {
        foreach ($this->evaluate() as $signal) {
            if ($signal['status'] === self::STATUS_FAIL) {
                return false;
            }
        }

        return true;
    }

    private function rulesSignal(): array
    {
        $rules = (array) config('entitlement_governance.rules', []);
        $missing = [];
        for ($i = 1; $i <= 24; $i++) {
            $key = sprintf('ENT-R%03d', $i);
            if (! array_key_exists($key, $rules)) {
                $missing[] = $key;
            }
        }

        return $missing === []
            ? $this->signal('rules', self::STATUS_PASS, 'ENT-R001..ENT-R024 present.')
            : $this->signal('rules', self::STATUS_FAIL, 'Missing rules: '.implode(', ', $missing));
    }

    private function safeDefaultsSignal(): array
    {
        $enforcement = (bool) config('entitlement_governance.runtime_enforcement_enabled', false);

        return $enforcement
            ? $this->signal('safe_defaults', self::STATUS_PASS, 'Runtime enforcement enabled by default.')
            : $this->signal('safe_defaults', self::STATUS_FAIL, 'Runtime enforcement must be enabled by default.');
    }

    private function failClosedSignal(): array
    {
        $failClosed = (bool) config('entitlement_governance.fail_closed_on_unknown_plan', false);
        $unlimitedAllowed = (bool) config('entitlement_governance.unknown_plan_grants_unlimited_allowed', true);

        return ($failClosed && ! $unlimitedAllowed)
            ? $this->signal('fail_closed', self::STATUS_PASS, 'Unknown plan fails closed; no unlimited fallback.')
            : $this->signal('fail_closed', self::STATUS_FAIL, 'Unknown plan must fail closed with no unlimited fallback.');
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'unknown_plan_grants_unlimited_allowed',
            'paid_invoice_lifts_manual_suspension_allowed',
            'failed_event_unlocks_entitlement_allowed',
            'tenant_route_can_mutate_entitlement_state_allowed',
            'silent_bypass_when_over_quota_allowed',
            'denied_access_without_audit_allowed',
        ];

        $violations = [];
        foreach ($flags as $flag) {
            if ((bool) config('entitlement_governance.'.$flag, false) === true) {
                $violations[] = $flag;
            }
        }

        return $violations === []
            ? $this->signal('guardrails', self::STATUS_PASS, 'All hard guardrails are false.')
            : $this->signal('guardrails', self::STATUS_FAIL, 'Guardrails violated: '.implode(', ', $violations));
    }

    private function featureKeysSignal(): array
    {
        $registry = (array) config('tenant_plan.entitlements', []);
        $keys = (array) config('entitlement_governance.feature_keys', []);

        // Export/report entitlement keys must also exist in the Sprint 26 registry.
        foreach ((array) config('entitlement_governance.exports', []) as $meta) {
            if (isset($meta['entitlement'])) {
                $keys[] = $meta['entitlement'];
            }
        }
        foreach ((array) config('entitlement_governance.reports', []) as $meta) {
            if (isset($meta['entitlement'])) {
                $keys[] = $meta['entitlement'];
            }
        }

        $unknown = array_values(array_diff(array_unique($keys), array_keys($registry)));

        return $unknown === []
            ? $this->signal('feature_keys', self::STATUS_PASS, 'All entitlement keys exist in the Sprint 26 registry.')
            : $this->signal('feature_keys', self::STATUS_FAIL, 'Unknown entitlement keys: '.implode(', ', $unknown));
    }

    private function middlewareSignal(): array
    {
        $aliases = Route::getMiddleware();
        $required = ['entitlement.write', 'entitlement.feature', 'entitlement.export', 'entitlement.report'];
        $missing = array_values(array_diff($required, array_keys($aliases)));

        return $missing === []
            ? $this->signal('middleware', self::STATUS_PASS, 'Entitlement middleware aliases registered.')
            : $this->signal('middleware', self::STATUS_FAIL, 'Missing middleware aliases: '.implode(', ', $missing));
    }

    private function auditRequirementSignal(): array
    {
        $persisted = (array) config('entitlement_governance.persist_decisions', []);
        $noAuditAllowed = (bool) config('entitlement_governance.denied_access_without_audit_allowed', true);

        return (in_array('denied', $persisted, true) && ! $noAuditAllowed)
            ? $this->signal('audit_requirement', self::STATUS_PASS, 'Denied decisions are audit-logged.')
            : $this->signal('audit_requirement', self::STATUS_FAIL, 'Denied access must be audit-logged.');
    }

    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
