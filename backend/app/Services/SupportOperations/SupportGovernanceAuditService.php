<?php

namespace App\Services\SupportOperations;

use App\Services\AndroidRuntime\DeviceRevocationService;

/**
 * Sprint 35 — audits that the support-operations governance is wired the way the
 * SUP rules require (config flags + rule registry + hard guardrails + doc
 * contract + read-only/impersonation posture). Pure configuration/structure
 * inspection: never mutates, charges, deploys, or leaks secrets. Produces
 * PASS/WARN/FAIL signals consumed by go-no-go (SUP-R030).
 */
class SupportGovernanceAuditService
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
            $this->guardrailsSignal(),
            $this->readOnlyDefaultSignal(),
            $this->reasonRequiredSignal(),
            $this->impersonationSafeSignal(),
            $this->viewersReadOnlySignal(),
            $this->deviceServiceSignal(),
            $this->redactionSignal(),
            $this->docsSignal(),
        ];
    }

    private function rulesPresentSignal(): array
    {
        $rules = (array) config('support_operations_governance.rules', []);
        $missing = [];
        for ($i = 1; $i <= 30; $i++) {
            $code = sprintf('SUP-R%03d', $i);
            if (! array_key_exists($code, $rules)) {
                $missing[] = $code;
            }
        }

        return $this->signal(
            'rules_registry',
            $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $missing === []
                ? 'All SUP-R001..SUP-R030 rules are declared in config.'
                : 'Missing support rules: '.implode(', ', $missing).'.',
        );
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'support_marks_invoice_paid_allowed',
            'support_unlocks_entitlement_allowed',
            'support_bypasses_payment_settlement_allowed',
            'support_lifts_manual_suspension_allowed',
            'support_reactivates_suspended_tenant_allowed',
            'support_mutates_state_without_governed_service_allowed',
            'support_console_public_or_tenant_mutation_allowed',
            'impersonation_enabled_without_governance_allowed',
            'impersonation_exposes_raw_credentials_allowed',
            'support_output_leaks_secret_or_pii_allowed',
        ];

        $violations = [];
        foreach ($flags as $flag) {
            if (config('support_operations_governance.'.$flag) !== false) {
                $violations[] = $flag;
            }
        }

        return $this->signal(
            'hard_guardrails',
            $violations === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $violations === []
                ? 'All hard support guardrails are locked false.'
                : 'Guardrail(s) not locked false: '.implode(', ', $violations).'.',
        );
    }

    private function readOnlyDefaultSignal(): array
    {
        $ok = config('support_operations_governance.read_only_by_default') === true;

        return $this->signal(
            'read_only_by_default',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'The support console is read-only by default.' : 'The support console is not read-only by default.',
        );
    }

    private function reasonRequiredSignal(): array
    {
        $required = config('support_operations_governance.reason_required_for_mutation') === true;
        $codes = (array) config('support_operations_governance.reason_codes', []);
        $ok = $required && $codes !== [];

        return $this->signal(
            'reason_required',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Support mutations require an enumerable reason code.' : 'Support mutations do not require a reason code.',
        );
    }

    private function impersonationSafeSignal(): array
    {
        $enabled = (bool) config('support_operations_governance.impersonation.enabled', false);
        $readOnlyOnly = config('support_operations_governance.impersonation.read_only_only') === true;
        $exposesRaw = (bool) config('support_operations_governance.impersonation.expose_raw_credentials', false);

        // Safe iff disabled, OR (enabled AND read-only-only AND never exposes raw).
        $ok = (! $enabled) || ($readOnlyOnly && ! $exposesRaw);

        return $this->signal(
            'impersonation_safe',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $enabled
                ? ($ok ? 'Impersonation is governed: read-only-safe, no raw credentials.' : 'Impersonation is enabled unsafely.')
                : 'Impersonation is disabled by default (safe).',
        );
    }

    private function viewersReadOnlySignal(): array
    {
        $viewers = (array) config('support_operations_governance.viewers_read_only', []);
        $expected = ['billing', 'payment', 'entitlement', 'onboarding', 'android_runtime'];
        $violations = [];
        foreach ($expected as $viewer) {
            if (($viewers[$viewer] ?? null) !== true) {
                $violations[] = $viewer;
            }
        }

        return $this->signal(
            'viewers_read_only',
            $violations === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $violations === []
                ? 'All support viewers are declared read-only.'
                : 'Viewer(s) not read-only: '.implode(', ', $violations).'.',
        );
    }

    private function deviceServiceSignal(): array
    {
        $ok = class_exists(DeviceRevocationService::class);

        return $this->signal(
            'device_revoke_uses_sprint34',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Device revoke delegates to the Sprint 34 DeviceRevocationService.' : 'The Sprint 34 DeviceRevocationService is missing.',
        );
    }

    private function redactionSignal(): array
    {
        $ok = (bool) config('support_operations_governance.redaction.required', false)
            && (bool) config('support_operations_governance.redaction.redact_metadata', false);

        return $this->signal(
            'redaction_required',
            $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Redaction is required for all support output/metadata.' : 'Redaction requirement is not enforced.',
        );
    }

    private function docsSignal(): array
    {
        $missing = [];
        foreach ((array) config('support_operations_governance.required_docs', []) as $doc) {
            if (! is_file(base_path('../'.$doc)) && ! is_file(base_path($doc)) && ! is_file(dirname(base_path()).'/'.$doc)) {
                $missing[] = $doc;
            }
        }

        return $this->signal(
            'docs_contract',
            $missing === [] ? self::STATUS_PASS : self::STATUS_WARN,
            $missing === [] ? 'Required support docs are present.' : 'Missing docs: '.implode(', ', $missing).'.',
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
