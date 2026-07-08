<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 26 — the canonical TPE-R rules and sprint foundation flags must stay
 * locked in config and docs so later sprints cannot silently drop them
 * (TPE-R011, TPE-R012). Sprint 25 TLS-R rules must remain intact (TPE-R012).
 */
class TenantPlanRulesLockTest extends TestCase
{
    public function test_all_tpe_rules_are_present_in_config(): void
    {
        $rules = (array) config('tenant_plan.rules');

        foreach (['TPE-R001', 'TPE-R002', 'TPE-R003', 'TPE-R004', 'TPE-R005', 'TPE-R006', 'TPE-R007', 'TPE-R008', 'TPE-R009', 'TPE-R010', 'TPE-R011', 'TPE-R012'] as $rule) {
            $this->assertArrayHasKey($rule, $rules, "Missing rule {$rule}");
            $this->assertNotEmpty($rules[$rule]);
        }
    }

    public function test_project_rules_doc_locks_sprint_26_runtime_rule(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString(
            'Sprint 26 Tenant Plan, Feature Entitlement & Usage Limit Governance Foundation Runtime Rule',
            $doc,
        );
        $this->assertStringContainsString('TPE-R004', $doc);
        $this->assertStringContainsString('TPE-R012', $doc);
        $this->assertStringContainsString(
            'docs/sprints/sprint-26-tenant-plan-feature-entitlement-usage-limit-governance-foundation.md',
            $doc,
        );
    }

    public function test_pos_foundation_locks_sprint_26(): void
    {
        $this->assertSame(
            'Tenant Plan, Feature Entitlement & Usage Limit Governance Foundation',
            config('pos_foundation.sprints.sprint_26'),
        );
        $this->assertTrue((bool) config('pos_foundation.rules.tenant_plan_source_of_truth_required'));
        $this->assertTrue((bool) config('pos_foundation.rules.tenant_lifecycle_precedes_entitlement_usage_sprint_26'));
        $this->assertTrue((bool) config('pos_foundation.rules.tenant_plan_go_no_go_required'));
    }

    public function test_automation_guardrails_are_all_disabled(): void
    {
        foreach ([
            'client_side_entitlement_authoritative',
            'suspended_tenant_can_be_overridden_allowed',
            'entitlement_computed_in_controller_allowed',
            'plan_assignment_without_platform_admin_allowed',
            'override_without_reason_allowed',
            'real_billing_charge_on_plan_change_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('tenant_plan.'.$flag), "Guardrail {$flag} must be false");
        }
    }

    public function test_sprint_25_lifecycle_rules_still_intact(): void
    {
        // TPE-R012 — Sprint 26 must not weaken Sprint 25 lifecycle governance.
        $tls = (array) config('tenant_lifecycle.rules');
        foreach (['TLS-R001', 'TLS-R002', 'TLS-R003', 'TLS-R004', 'TLS-R005', 'TLS-R006', 'TLS-R007', 'TLS-R008', 'TLS-R009', 'TLS-R010'] as $rule) {
            $this->assertArrayHasKey($rule, $tls, "Sprint 25 rule {$rule} must remain");
        }
        $this->assertContains('suspended', (array) config('tenant_lifecycle.blocked_statuses'));
    }

    public function test_plan_catalogue_keys_present(): void
    {
        $keys = (array) config('tenant_plan.plan_keys');
        foreach (['starter', 'growth', 'professional', 'enterprise'] as $key) {
            $this->assertContains($key, $keys);
        }
        $this->assertContains((string) config('tenant_plan.default_plan'), $keys);
    }
}
