<?php

namespace App\Services\TenantOnboarding;

/**
 * Sprint 33 — audits that the onboarding governance is wired the way the rules
 * require (config flags + rule registry + hard guardrails + doc contract). It is
 * pure configuration/structure inspection: it never mutates, charges, deploys,
 * or leaks secrets. Produces PASS/WARN/FAIL signals consumed by go-no-go.
 */
class OnboardingGovernanceAuditService
{
    public const STATUS_PASS = 'PASS';
    public const STATUS_WARN = 'WARN';
    public const STATUS_FAIL = 'FAIL';

    /**
     * @return array<int, array{key: string, status: string, message: string}>
     */
    public function evaluate(): array
    {
        return [
            $this->rulesPresentSignal(),
            $this->failClosedSignal(),
            $this->guardrailsSignal(),
            $this->publicSignupSignal(),
            $this->trialBoundedSignal(),
            $this->auditRequiredSignal(),
            $this->docsSignal(),
        ];
    }

    private function rulesPresentSignal(): array
    {
        $rules = (array) config('onboarding_governance.rules', []);
        $missing = [];

        for ($i = 1; $i <= 26; $i++) {
            $code = sprintf('ONB-R%03d', $i);
            if (! array_key_exists($code, $rules)) {
                $missing[] = $code;
            }
        }

        return $this->signal(
            'rules_registry',
            $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $missing === []
                ? 'All ONB-R001..ONB-R026 rules are declared in config.'
                : 'Missing onboarding rules: '.implode(', ', $missing).'.',
        );
    }

    private function failClosedSignal(): array
    {
        $ok = config('onboarding_governance.unknown_plan_grants_unlimited_allowed') === false;

        return $this->signal(
            'fail_closed_unknown_plan',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Unknown plan fails closed (no unlimited fallback).' : 'Unknown plan is allowed to grant unlimited — forbidden.',
        );
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'unknown_plan_grants_unlimited_allowed',
            'onboarding_bypasses_entitlement_service_allowed',
            'onboarding_marks_invoice_paid_directly_allowed',
            'failed_payment_activates_paid_access_allowed',
            'paid_invoice_lifts_manual_suspension_allowed',
            'public_route_can_mutate_onboarding_lifecycle_allowed',
            'tenant_route_can_mutate_onboarding_lifecycle_allowed',
            'raw_credential_in_output_allowed',
        ];

        $violations = [];

        foreach ($flags as $flag) {
            if (config('onboarding_governance.'.$flag) !== false) {
                $violations[] = $flag;
            }
        }

        return $this->signal(
            'hard_guardrails',
            $violations === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $violations === []
                ? 'All hard onboarding guardrails are locked false.'
                : 'Guardrail(s) not locked false: '.implode(', ', $violations).'.',
        );
    }

    private function publicSignupSignal(): array
    {
        $disabled = config('onboarding_governance.public_self_signup_mutation_enabled') === false;

        return $this->signal(
            'public_signup_disabled_default',
            $disabled ? self::STATUS_PASS : self::STATUS_WARN,
            $disabled
                ? 'Public self-signup mutation is disabled by default.'
                : 'Public self-signup mutation is enabled — verify the signed approval-token flow.',
        );
    }

    private function trialBoundedSignal(): array
    {
        $days = (int) config('onboarding_governance.trial.default_duration_days', 0);
        $max = (int) config('onboarding_governance.trial.max_duration_days', 0);
        $ok = $days > 0 && $max > 0 && $days <= $max;

        return $this->signal(
            'trial_time_bounded',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? "Trial is time-bounded ({$days}d, max {$max}d)." : 'Trial duration is not properly bounded.',
        );
    }

    private function auditRequiredSignal(): array
    {
        $ok = (bool) config('onboarding_governance.audit.required', false)
            && (bool) config('onboarding_governance.audit.redact_metadata', false);

        return $this->signal(
            'audit_required',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Audit is required and metadata redaction is on.' : 'Audit/redaction requirement is not enforced.',
        );
    }

    private function docsSignal(): array
    {
        $missing = [];

        foreach ((array) config('onboarding_governance.required_docs', []) as $doc) {
            if (! is_file(base_path('../'.$doc)) && ! is_file(base_path($doc)) && ! is_file(dirname(base_path()).'/'.$doc)) {
                $missing[] = $doc;
            }
        }

        return $this->signal(
            'docs_contract',
            $missing === [] ? self::STATUS_PASS : self::STATUS_WARN,
            $missing === [] ? 'Required onboarding docs are present.' : 'Missing docs: '.implode(', ', $missing).'.',
        );
    }

    /**
     * @return array{key: string, status: string, message: string}
     */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
