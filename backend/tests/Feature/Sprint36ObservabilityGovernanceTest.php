<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 36 — governance/config posture (OBS-R001..R032). Pure config/command
 * assertions; no DB fixtures required.
 */
class Sprint36ObservabilityGovernanceTest extends TestCase
{
    public function test_all_obs_rules_present_in_config(): void
    {
        $rules = config('observability_governance.rules');
        for ($i = 1; $i <= 32; $i++) {
            $this->assertArrayHasKey(sprintf('OBS-R%03d', $i), $rules);
        }
    }

    public function test_all_obs_rules_present_in_pos_foundation(): void
    {
        $rules = config('pos_foundation.observability_rules_sprint_36');
        $this->assertIsArray($rules);
        for ($i = 1; $i <= 32; $i++) {
            $this->assertArrayHasKey(sprintf('OBS-R%03d', $i), $rules);
        }
    }

    public function test_hard_guardrails_are_locked_false(): void
    {
        foreach ([
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
        ] as $flag) {
            $this->assertFalse(config('observability_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_safe_defaults(): void
    {
        $this->assertTrue(config('observability_governance.read_only_by_default'));
        $this->assertTrue(config('observability_governance.reason_required_for_mutation'));
        $this->assertTrue(config('observability_governance.redaction.required'));
        $this->assertTrue(config('observability_governance.audit.required'));
    }

    public function test_job_retry_disabled_by_default(): void
    {
        $this->assertFalse(config('observability_governance.job_retry.enabled'));
        $this->assertTrue(config('observability_governance.job_retry.reason_required'));
        $this->assertTrue(config('observability_governance.job_retry.idempotent_only'));
    }

    public function test_alerting_is_vendor_neutral_and_ci_safe(): void
    {
        $this->assertTrue(config('observability_governance.alerting.vendor_neutral'));
        $this->assertFalse(config('observability_governance.alerting.external_service_required'));
    }

    public function test_incident_suggestion_does_not_auto_create_incident_on_scan(): void
    {
        $this->assertFalse(config('observability_governance.incident_suggestion.auto_create_incident_on_scan'));
    }

    public function test_thresholds_are_config_driven(): void
    {
        $thresholds = config('observability_governance.thresholds');
        $this->assertIsArray($thresholds);
        foreach (['queue_pending_watch', 'failed_jobs_watch', 'scheduler_stale_seconds', 'billing_grace_days', 'entitlement_denial_watch'] as $key) {
            $this->assertArrayHasKey($key, $thresholds);
        }
    }

    public function test_reason_codes_are_enumerable(): void
    {
        $codes = config('observability_governance.reason_codes');
        $this->assertIsArray($codes);
        $this->assertContains('operator_review', $codes);
        $this->assertContains('governed_retry', $codes);
    }

    public function test_governance_audit_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('observability:governance-audit'));
    }

    public function test_go_no_go_is_go(): void
    {
        $this->assertSame(0, Artisan::call('observability:go-no-go', ['--strict' => true]));
    }

    public function test_go_no_go_json_has_no_secret_or_pii(): void
    {
        Artisan::call('observability:go-no-go', ['--json' => true]);
        $output = Artisan::output();
        $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key|sk_live_/i', $output);
    }

    public function test_all_eleven_observability_commands_registered(): void
    {
        $registered = array_keys(Artisan::all());
        foreach ((array) config('observability_governance.observability_commands') as $command) {
            $this->assertContains($command, $registered, $command.' must be registered');
        }
    }

    public function test_prior_sprint_gates_still_registered(): void
    {
        $registered = array_keys(Artisan::all());
        foreach ((array) config('observability_governance.required_commands') as $command) {
            $this->assertContains($command, $registered, $command.' prior gate must be registered');
        }
    }
}
