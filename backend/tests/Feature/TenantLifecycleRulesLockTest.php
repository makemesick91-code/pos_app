<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 25 — the canonical TLS-R rules and sprint foundation flags must stay
 * locked in config and docs so later sprints cannot silently drop them
 * (TLS-R010).
 */
class TenantLifecycleRulesLockTest extends TestCase
{
    public function test_all_tls_rules_are_present_in_config(): void
    {
        $rules = (array) config('tenant_lifecycle.rules');

        foreach (['TLS-R001', 'TLS-R002', 'TLS-R003', 'TLS-R004', 'TLS-R005', 'TLS-R006', 'TLS-R007', 'TLS-R008', 'TLS-R009', 'TLS-R010'] as $rule) {
            $this->assertArrayHasKey($rule, $rules, "Missing rule {$rule}");
            $this->assertNotEmpty($rules[$rule]);
        }
    }

    public function test_project_rules_doc_locks_sprint_25_runtime_rule(): void
    {
        $doc = file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString(
            'Sprint 25 Tenant Lifecycle Enforcement & Manual Suspension Governance Foundation Runtime Rule',
            (string) $doc,
        );
        $this->assertStringContainsString('TLS-R004', (string) $doc);
        $this->assertStringContainsString(
            'docs/sprints/sprint-25-tenant-lifecycle-enforcement-manual-suspension-governance-foundation.md',
            (string) $doc,
        );
    }

    public function test_pos_foundation_locks_sprint_25(): void
    {
        $this->assertSame(
            'Tenant Lifecycle Enforcement & Manual Suspension Governance Foundation',
            config('pos_foundation.sprints.sprint_25'),
        );
        $this->assertTrue((bool) config('pos_foundation.rules.tenant_lifecycle_source_of_truth_required'));
        $this->assertTrue((bool) config('pos_foundation.rules.manual_suspension_precedence_over_automation_sprint_25'));
    }

    public function test_blocked_statuses_include_suspended(): void
    {
        $blocked = (array) config('tenant_lifecycle.blocked_statuses');
        $this->assertContains('suspended', $blocked);
    }

    public function test_automation_guardrails_are_all_disabled(): void
    {
        foreach ([
            'auto_tenant_suspension_allowed',
            'auto_tenant_reactivation_allowed',
            'dunning_can_override_manual_suspension_allowed',
            'renewal_can_override_manual_suspension_allowed',
            'client_side_enforcement_authoritative',
            'public_tenant_suspension_api_allowed',
            'real_notification_sending_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('tenant_lifecycle.'.$flag), "Guardrail {$flag} must be false");
        }
    }
}
