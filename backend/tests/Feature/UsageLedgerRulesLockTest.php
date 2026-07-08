<?php

namespace Tests\Feature;

use App\Services\TenantPlan\TenantUsageMeter;
use Tests\TestCase;

/**
 * Sprint 28 — the canonical ULR-R rules, guardrail flags, and the meterable
 * report-export meter must stay locked in config and docs so later sprints cannot
 * silently drop them (ULR-R014, ULR-R015, ULR-R016). Prior-sprint rules remain.
 */
class UsageLedgerRulesLockTest extends TestCase
{
    public function test_all_ulr_rules_present_in_config(): void
    {
        $rules = (array) config('usage_ledger_anomaly.rules');
        for ($i = 1; $i <= 16; $i++) {
            $key = sprintf('ULR-R%03d', $i);
            $this->assertArrayHasKey($key, $rules, "Missing rule {$key}");
            $this->assertNotEmpty($rules[$key]);
        }
    }

    public function test_project_rules_doc_locks_sprint_28_runtime_rule(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));

        $this->assertStringContainsString(
            'Sprint 28 Usage Ledger Anomaly Detection & Governed Repair Foundation Runtime Rule',
            $doc,
        );
        $this->assertStringContainsString('ULR-R007', $doc);
        $this->assertStringContainsString('ULR-R010', $doc);
        $this->assertStringContainsString('ULR-R016', $doc);
        $this->assertStringContainsString(
            'docs/sprints/sprint-28-usage-ledger-anomaly-detection-governed-repair-foundation.md',
            $doc,
        );
    }

    public function test_pos_foundation_locks_sprint_28(): void
    {
        $this->assertSame(
            'Usage Ledger Anomaly Detection & Governed Repair Foundation',
            config('pos_foundation.sprints.sprint_28'),
        );
        $this->assertTrue((bool) config('pos_foundation.rules.usage_ledger_anomaly_detection_read_only_sprint_28'));
        $this->assertTrue((bool) config('pos_foundation.rules.usage_ledger_governed_repair_dry_run_default_sprint_28'));
        $this->assertTrue((bool) config('pos_foundation.rules.usage_ledger_go_no_go_required_sprint_28'));
    }

    public function test_sprint_28_guardrails_all_disabled(): void
    {
        foreach ([
            'anomaly_detector_may_mutate_ledger_allowed',
            'repair_apply_without_dry_run_default_allowed',
            'repair_apply_without_reason_actor_allowed',
            'usage_ledger_mutation_route_allowed',
            'repair_may_delete_original_event_allowed',
            'effective_usage_negative_allowed',
        ] as $flag) {
            $this->assertFalse((bool) config('usage_ledger_anomaly.'.$flag), "Guardrail {$flag} must be false");
        }
    }

    public function test_report_exports_meter_remains_meterable_after_sprint_28(): void
    {
        $limits = (array) config('tenant_plan.usage_limits');
        $this->assertTrue((bool) ($limits['reports.exports.monthly']['meterable'] ?? false));
    }

    public function test_is_meterable_resolves_dotted_keys_literally(): void
    {
        // Regression for the Sprint 27 latent config-dot-path bug: dotted usage
        // limit keys must resolve to their meterable flag, not to a nested path.
        $meter = app(TenantUsageMeter::class);
        $this->assertTrue($meter->isMeterable('reports.exports.monthly'));
        $this->assertTrue($meter->isMeterable('transactions.monthly'));
        $this->assertFalse($meter->isMeterable('nonexistent.meter.key'));
    }

    public function test_prior_sprint_rule_families_intact(): void
    {
        $doc = (string) file_get_contents(base_path('../docs/PROJECT_RULES.md'));
        $this->assertStringContainsString('TLS-R004', $doc); // Sprint 25
        $this->assertStringContainsString('TPE-R012', $doc); // Sprint 26
        $this->assertStringContainsString('UEL-R015', $doc); // Sprint 27
    }
}
