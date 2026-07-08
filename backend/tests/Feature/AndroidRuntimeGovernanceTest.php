<?php

namespace Tests\Feature;

use App\Services\AndroidRuntime\AndroidRuntimeGoNoGoService;
use App\Services\AndroidRuntime\AndroidRuntimeGovernanceAuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 34 — governance/config contract for the Android runtime (ADR-R001..R030).
 */
class AndroidRuntimeGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_adr_rules_declared_in_config(): void
    {
        $rules = (array) config('android_runtime_governance.rules');

        for ($i = 1; $i <= 30; $i++) {
            $this->assertArrayHasKey(sprintf('ADR-R%03d', $i), $rules);
        }
    }

    public function test_hard_guardrails_are_locked_false(): void
    {
        foreach ([
            'raw_activation_token_returned_after_creation_allowed',
            'raw_activation_token_stored_allowed',
            'android_marks_invoice_paid_allowed',
            'android_unlocks_entitlement_locally_allowed',
            'sync_bypasses_pos_domain_service_allowed',
            'revoked_device_can_sync_allowed',
            'duplicate_client_uuid_double_mutation_allowed',
            'runtime_bypasses_entitlement_service_allowed',
            'manual_suspension_overridable_by_billing_allowed',
            'raw_credential_in_output_allowed',
        ] as $flag) {
            $this->assertFalse(config('android_runtime_governance.'.$flag), $flag.' must be false');
        }
    }

    public function test_runtime_behavior_is_fail_closed(): void
    {
        $this->assertSame('block', config('android_runtime_governance.runtime_behavior.suspended'));
        $this->assertContains(config('android_runtime_governance.runtime_behavior.unpaid_past_grace'), ['block', 'read_only']);
        $this->assertContains(config('android_runtime_governance.runtime_behavior.trial_expired'), ['block', 'read_only']);
    }

    public function test_adr_rules_present_in_foundation_and_project_rules(): void
    {
        $foundation = (array) config('pos_foundation.android_runtime_rules_sprint_34');
        $this->assertArrayHasKey('ADR-R001', $foundation);
        $this->assertArrayHasKey('ADR-R030', $foundation);

        $projectRules = file_get_contents(base_path('../docs/PROJECT_RULES.md'));
        $this->assertStringContainsString('ADR-R001', $projectRules);
        $this->assertStringContainsString('ADR-R030', $projectRules);
    }

    public function test_governance_audit_has_no_fail_signal(): void
    {
        $signals = app(AndroidRuntimeGovernanceAuditService::class)->evaluate();

        foreach ($signals as $signal) {
            $this->assertNotSame(
                AndroidRuntimeGovernanceAuditService::STATUS_FAIL,
                $signal['status'],
                $signal['key'].': '.$signal['message'],
            );
        }
    }

    public function test_go_no_go_is_not_no_go(): void
    {
        $report = app(AndroidRuntimeGoNoGoService::class)->evaluate();

        $this->assertNotSame(AndroidRuntimeGoNoGoService::DECISION_NO_GO, $report['decision']);
    }
}
