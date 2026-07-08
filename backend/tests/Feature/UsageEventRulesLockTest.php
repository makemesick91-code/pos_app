<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 27 — the canonical UEL-R rules and foundation flags must stay locked in
 * config and docs so later sprints cannot silently drop them (UEL-R014, UEL-R015).
 * Sprint 25 TLS-R and Sprint 26 TPE-R rules must remain intact (UEL-R015).
 */
class UsageEventRulesLockTest extends TestCase
{
    public function test_all_uel_rules_present_in_config(): void
    {
        $rules = (array) config('usage_event_ledger.rules');
        foreach ([
            'UEL-R001', 'UEL-R002', 'UEL-R003', 'UEL-R004', 'UEL-R005', 'UEL-R006',
            'UEL-R007', 'UEL-R008', 'UEL-R009', 'UEL-R010', 'UEL-R011', 'UEL-R012',
            'UEL-R013', 'UEL-R014', 'UEL-R015',
        ] as $rule) {
            $this->assertArrayHasKey($rule, $rules, "Missing rule {$rule}");
            $this->assertNotEmpty($rules[$rule]);
        }
    }

    public function test_project_rules_doc_locks_sprint_27_runtime_rule(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString(
            'Sprint 27 Report Export Metering & Usage Event Ledger Governance Foundation Runtime Rule',
            $doc,
        );
        $this->assertStringContainsString('UEL-R004', $doc);
        $this->assertStringContainsString('UEL-R008', $doc);
        $this->assertStringContainsString('UEL-R015', $doc);
        $this->assertStringContainsString(
            'docs/sprints/sprint-27-report-export-metering-usage-event-ledger-governance-foundation.md',
            $doc,
        );
    }

    public function test_pos_foundation_locks_sprint_27(): void
    {
        $this->assertSame(
            'Report Export Metering & Usage Event Ledger Governance Foundation',
            config('pos_foundation.sprints.sprint_27'),
        );
        $this->assertTrue((bool) config('pos_foundation.rules.usage_event_ledger_source_of_truth_required'));
        $this->assertTrue((bool) config('pos_foundation.rules.report_export_metering_required_sprint_27'));
        $this->assertTrue((bool) config('pos_foundation.rules.report_export_metering_go_no_go_required'));
    }

    public function test_usage_ledger_guardrails_all_disabled(): void
    {
        foreach ([
            'usage_ledger_mutable_in_runtime_allowed',
            'client_side_report_export_authoritative',
            'usage_event_metadata_may_store_secrets_allowed',
            'failed_export_counts_usage_allowed',
            'cross_tenant_usage_events_in_runtime_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('usage_event_ledger.'.$flag), "Guardrail {$flag} must be false");
        }
    }

    public function test_report_exports_meter_is_meterable(): void
    {
        $limits = (array) config('tenant_plan.usage_limits');
        $this->assertTrue((bool) ($limits['reports.exports.monthly']['meterable'] ?? false));
    }

    public function test_prior_sprint_rules_still_intact(): void
    {
        // UEL-R015 — Sprint 27 must not weaken Sprint 25/26 governance.
        $tls = (array) config('tenant_lifecycle.rules');
        foreach (['TLS-R001', 'TLS-R004', 'TLS-R010'] as $rule) {
            $this->assertArrayHasKey($rule, $tls, "Sprint 25 rule {$rule} must remain");
        }
        $tpe = (array) config('tenant_plan.rules');
        foreach (['TPE-R001', 'TPE-R004', 'TPE-R012'] as $rule) {
            $this->assertArrayHasKey($rule, $tpe, "Sprint 26 rule {$rule} must remain");
        }
        $this->assertContains('suspended', (array) config('tenant_lifecycle.blocked_statuses'));
    }
}
