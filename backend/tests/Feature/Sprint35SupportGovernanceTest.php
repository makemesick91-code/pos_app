<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Sprint 35 — governance/config posture (SUP-R001..R030). Pure config/command
 * assertions; no DB fixtures required.
 */
class Sprint35SupportGovernanceTest extends TestCase
{
    public function test_all_sup_rules_present_in_config(): void
    {
        $rules = config('support_operations_governance.rules');
        for ($i = 1; $i <= 30; $i++) {
            $this->assertArrayHasKey(sprintf('SUP-R%03d', $i), $rules);
        }
    }

    public function test_all_sup_rules_present_in_pos_foundation(): void
    {
        $rules = config('pos_foundation.support_operations_rules_sprint_35');
        $this->assertIsArray($rules);
        for ($i = 1; $i <= 30; $i++) {
            $this->assertArrayHasKey(sprintf('SUP-R%03d', $i), $rules);
        }
    }

    public function test_hard_guardrails_are_locked_false(): void
    {
        foreach ([
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
        ] as $flag) {
            $this->assertFalse(config('support_operations_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_safe_defaults(): void
    {
        $this->assertTrue(config('support_operations_governance.read_only_by_default'));
        $this->assertTrue(config('support_operations_governance.reason_required_for_mutation'));
        $this->assertTrue(config('support_operations_governance.redaction.required'));
    }

    public function test_impersonation_disabled_by_default(): void
    {
        $this->assertFalse(config('support_operations_governance.impersonation.enabled'));
        $this->assertFalse(config('support_operations_governance.impersonation.expose_raw_credentials'));
        $this->assertTrue(config('support_operations_governance.impersonation.read_only_only'));
    }

    public function test_governance_audit_command_passes(): void
    {
        $this->assertSame(0, Artisan::call('support-ops:governance-audit'));
    }

    public function test_go_no_go_is_go(): void
    {
        $this->assertSame(0, Artisan::call('support-ops:go-no-go', ['--strict' => true]));
    }

    public function test_go_no_go_json_has_no_secret_or_pii(): void
    {
        Artisan::call('support-ops:go-no-go', ['--json' => true]);
        $output = Artisan::output();
        $this->assertDoesNotMatchRegularExpression('/password|secret|api_key|server_key|private_key|sk_live_/i', $output);
    }
}
