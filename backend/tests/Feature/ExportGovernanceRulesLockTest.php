<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Sprint 29 — the canonical EGC-R rules, guardrail flags, and the meterable
 * report-export meter must stay locked in config and docs so later sprints cannot
 * silently drop them (EGC-R013, EGC-R014, EGC-R015). Prior-sprint rules remain.
 */
class ExportGovernanceRulesLockTest extends TestCase
{
    public function test_all_egc_rules_present_in_config(): void
    {
        $rules = (array) config('export_governance.rules');
        for ($i = 1; $i <= 15; $i++) {
            $key = sprintf('EGC-R%03d', $i);
            $this->assertArrayHasKey($key, $rules, "Missing rule {$key}");
            $this->assertNotEmpty($rules[$key]);
        }
    }

    public function test_project_rules_doc_locks_sprint_29_runtime_rule(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString(
            'Sprint 29 Multi-Export Route Metering Coverage & Export Governance Expansions Foundation Runtime Rule',
            $doc,
        );
        $this->assertStringContainsString('EGC-R003', $doc);
        $this->assertStringContainsString('EGC-R008', $doc);
        $this->assertStringContainsString('EGC-R015', $doc);
        $this->assertStringContainsString(
            'docs/sprints/sprint-29-multi-export-route-metering-coverage-export-governance-expansions.md',
            $doc,
        );
    }

    public function test_pos_foundation_locks_sprint_29(): void
    {
        $this->assertSame(
            'Multi-Export Route Metering Coverage & Export Governance Expansions',
            config('pos_foundation.sprints.sprint_29'),
        );
        $this->assertTrue((bool) config('pos_foundation.rules.export_route_registry_source_of_truth_required_sprint_29'));
        $this->assertTrue((bool) config('pos_foundation.rules.metered_export_lifecycle_precedes_entitlement_usage_sprint_29'));
        $this->assertTrue((bool) config('pos_foundation.rules.export_governance_go_no_go_required_sprint_29'));
    }

    public function test_sprint_29_guardrails_all_disabled(): void
    {
        foreach ([
            'export_metering_bypass_route_allowed',
            'unregistered_export_route_allowed',
            'export_exemption_without_reason_allowed',
            'client_side_export_authoritative',
            'blocked_export_counts_usage_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('export_governance.'.$flag), "Guardrail {$flag} must be false");
        }
    }

    public function test_report_exports_meter_remains_meterable_after_sprint_29(): void
    {
        $limits = (array) config('tenant_plan.usage_limits');
        $this->assertTrue((bool) ($limits['reports.exports.monthly']['meterable'] ?? false));
    }

    public function test_prior_sprint_rule_families_intact(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));
        $this->assertStringContainsString('TLS-R004', $doc);  // Sprint 25
        $this->assertStringContainsString('TPE-R012', $doc);  // Sprint 26
        $this->assertStringContainsString('UEL-R015', $doc);  // Sprint 27
        $this->assertStringContainsString('ULR-R016', $doc);  // Sprint 28
    }
}
