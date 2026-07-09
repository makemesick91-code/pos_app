<?php

namespace App\Services\Observability;

/**
 * Sprint 36 — audits that the observability governance is wired the way the OBS
 * rules require (config flags + rule registry + hard guardrails + doc contract +
 * read-only/retry posture + threshold config). Pure configuration/structure
 * inspection: never mutates, charges, deploys, or leaks secrets. Produces
 * PASS/WARN/FAIL signals consumed by go-no-go (OBS-R032).
 */
class ObservabilityGovernanceAuditService
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
            $this->publicHealthSafeSignal(),
            $this->jobRetrySafeSignal(),
            $this->thresholdsConfigDrivenSignal(),
            $this->anomalySourcesSignal(),
            $this->redactionSignal(),
            $this->auditSignal(),
            $this->vendorNeutralSignal(),
            $this->docsSignal(),
        ];
    }

    private function rulesPresentSignal(): array
    {
        $rules = (array) config('observability_governance.rules', []);
        $missing = [];
        for ($i = 1; $i <= 32; $i++) {
            $code = sprintf('OBS-R%03d', $i);
            if (! array_key_exists($code, $rules)) {
                $missing[] = $code;
            }
        }

        return $this->signal(
            'rules_registry',
            $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $missing === []
                ? 'All OBS-R001..OBS-R032 rules are declared in config.'
                : 'Missing observability rules: '.implode(', ', $missing).'.',
        );
    }

    private function guardrailsSignal(): array
    {
        $flags = [
            'diagnostics_mark_invoice_paid_allowed',
            'diagnostics_unlock_entitlement_allowed',
            'diagnostics_reactivate_tenant_or_device_allowed',
            'diagnostics_bypass_manual_suspension_allowed',
            'diagnostics_mutate_domain_without_governed_service_allowed',
            'observability_public_endpoint_exposes_tenant_or_secret_allowed',
            'observability_output_leaks_secret_or_pii_allowed',
            'incident_suggestion_auto_mutates_tenant_allowed',
            'queue_retry_without_governance_allowed',
            'external_monitoring_vendor_required_in_ci_allowed',
        ];

        $violations = [];
        foreach ($flags as $flag) {
            if (config('observability_governance.'.$flag) !== false) {
                $violations[] = $flag;
            }
        }

        return $this->signal(
            'hard_guardrails',
            $violations === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $violations === []
                ? 'All hard observability guardrails are locked false.'
                : 'Guardrail(s) not locked false: '.implode(', ', $violations).'.',
        );
    }

    private function readOnlyDefaultSignal(): array
    {
        $ok = config('observability_governance.read_only_by_default') === true;

        return $this->signal('read_only_by_default', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Observability is read-only by default.' : 'Observability is not read-only by default.');
    }

    private function reasonRequiredSignal(): array
    {
        $required = config('observability_governance.reason_required_for_mutation') === true;
        $codes = (array) config('observability_governance.reason_codes', []);
        $ok = $required && $codes !== [];

        return $this->signal('reason_required', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Observability mutations require an enumerable reason code.' : 'Observability mutations do not require a reason code.');
    }

    private function publicHealthSafeSignal(): array
    {
        // Safe by construction: the endpoints only return ok/degraded + timestamp,
        // and the guardrail forbids tenant/secret exposure. This asserts the flag.
        $ok = config('observability_governance.observability_public_endpoint_exposes_tenant_or_secret_allowed') === false;

        return $this->signal('public_health_minimal', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Public health endpoints are minimal and non-tenant by contract.' : 'Public health endpoints may expose tenant/secret data.');
    }

    private function jobRetrySafeSignal(): array
    {
        $enabled = (bool) config('observability_governance.job_retry.enabled', false);
        $reasonRequired = config('observability_governance.job_retry.reason_required') === true;
        $idempotentOnly = config('observability_governance.job_retry.idempotent_only') === true;
        // Safe iff disabled, OR (enabled AND reason-required AND idempotent-only).
        $ok = (! $enabled) || ($reasonRequired && $idempotentOnly);

        return $this->signal('job_retry_safe', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $enabled
                ? ($ok ? 'Queue retry is governed: reason-required + idempotent-only.' : 'Queue retry is enabled unsafely.')
                : 'Queue retry is disabled by default (safe).');
    }

    private function thresholdsConfigDrivenSignal(): array
    {
        $thresholds = (array) config('observability_governance.thresholds', []);
        $expected = [
            'queue_pending_watch', 'failed_jobs_watch', 'scheduler_stale_seconds',
            'sync_failed_batch_watch', 'billing_grace_days', 'entitlement_denial_watch',
            'onboarding_failed_watch', 'export_denial_watch',
        ];
        $missing = array_values(array_diff($expected, array_keys($thresholds)));

        return $this->signal('thresholds_config_driven', $missing === [] ? self::STATUS_PASS : self::STATUS_FAIL,
            $missing === [] ? 'Anomaly thresholds are config-driven.' : 'Missing thresholds: '.implode(', ', $missing).'.');
    }

    private function anomalySourcesSignal(): array
    {
        $ok = class_exists(AndroidSyncAnomalyService::class)
            && class_exists(BillingPaymentAnomalyService::class)
            && class_exists(EntitlementAnomalyService::class)
            && class_exists(OnboardingAnomalyService::class)
            && class_exists(ExportReportAnomalyService::class);

        return $this->signal('anomaly_detectors_wired', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'All Sprint 30–35-sourced anomaly detectors are present.' : 'One or more anomaly detectors are missing.');
    }

    private function redactionSignal(): array
    {
        $ok = (bool) config('observability_governance.redaction.required', false)
            && (bool) config('observability_governance.redaction.redact_metadata', false);

        return $this->signal('redaction_required', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Redaction is required for all observability output/metadata.' : 'Redaction requirement is not enforced.');
    }

    private function auditSignal(): array
    {
        $ok = (bool) config('observability_governance.audit.required', false);

        return $this->signal('audit_required', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Platform-admin diagnostic mutations are audited.' : 'Audit requirement is not enforced.');
    }

    private function vendorNeutralSignal(): array
    {
        $vendorNeutral = (bool) config('observability_governance.alerting.vendor_neutral', false);
        $externalRequired = (bool) config('observability_governance.alerting.external_service_required', true);
        $ok = $vendorNeutral && ! $externalRequired;

        return $this->signal('alerting_vendor_neutral', $ok ? self::STATUS_PASS : self::STATUS_FAIL,
            $ok ? 'Alert readiness is vendor-neutral and CI-safe.' : 'Alert readiness requires an external vendor.');
    }

    private function docsSignal(): array
    {
        $missing = [];
        foreach ((array) config('observability_governance.required_docs', []) as $doc) {
            if (! is_file(base_path('../'.$doc)) && ! is_file(base_path($doc)) && ! is_file(dirname(base_path()).'/'.$doc)) {
                $missing[] = $doc;
            }
        }

        return $this->signal('docs_contract', $missing === [] ? self::STATUS_PASS : self::STATUS_WARN,
            $missing === [] ? 'Required observability docs are present.' : 'Missing docs: '.implode(', ', $missing).'.');
    }

    /**
     * @return array{key: string, status: string, message: string}
     */
    private function signal(string $key, string $status, string $message): array
    {
        return ['key' => $key, 'status' => $status, 'message' => $message];
    }
}
