<?php

namespace Tests\Feature;

use App\Services\ExportGovernance\ExportGovernanceAuditService;
use App\Services\ExportGovernance\ExportRouteDiscoveryService;
use App\Services\ExportGovernance\ExportRouteRegistry;
use Tests\TestCase;

/**
 * Sprint 29 — the export governance registry + server-side discovery are wired
 * and used (not docs-only): the metered export route is registered, exemptions
 * carry reasons, and the scanner detects an unregistered export-like route
 * (EGC-R001, EGC-R002, EGC-R010).
 */
class ExportGovernanceRegistryTest extends TestCase
{
    public function test_registry_lists_metered_daily_sales_export(): void
    {
        $registry = app(ExportRouteRegistry::class);

        $this->assertArrayHasKey('GET api/v1/reports/daily-sales/export.csv', $registry->metered());
        $meta = $registry->find('GET api/v1/reports/daily-sales/export.csv');
        $this->assertSame('reports.exports.monthly', $meta['meter_key']);
        $this->assertSame('report.exported', $meta['event_key']);
        $this->assertSame('reports.basic', $meta['entitlement']);
        $this->assertNotEmpty($meta['idempotency_strategy']);
        $this->assertTrue($meta['metadata_sanitized']);
    }

    public function test_canonical_taxonomy_matches_ledger_config(): void
    {
        $registry = app(ExportRouteRegistry::class);

        $this->assertSame('reports.exports.monthly', $registry->meterKey());
        $this->assertSame((string) config('usage_event_ledger.report_export_meter_key'), $registry->meterKey());
        $this->assertSame((string) config('usage_event_ledger.report_export_event_key'), $registry->eventKey());
        $this->assertSame((string) config('usage_event_ledger.report_export_event_category'), $registry->eventCategory());
    }

    public function test_every_exempt_route_has_a_reason(): void
    {
        $registry = app(ExportRouteRegistry::class);

        $exempt = $registry->exempt();
        $this->assertNotEmpty($exempt);
        foreach ($exempt as $signature => $meta) {
            $this->assertNotEmpty($meta['exempt_reason'] ?? null, "{$signature} exemption must carry a reason");
            $this->assertFalse((bool) ($meta['metering_enabled'] ?? false), "{$signature} exemption must not enable metering");
        }
    }

    public function test_discovery_finds_the_daily_sales_export_and_no_gaps(): void
    {
        $discovered = app(ExportRouteDiscoveryService::class)->discover();
        $signatures = array_column($discovered, 'signature');

        $this->assertContains('GET api/v1/reports/daily-sales/export.csv', $signatures);
        $this->assertSame([], app(ExportRouteDiscoveryService::class)->unregistered());
    }

    public function test_audit_detects_an_unregistered_export_like_route(): void
    {
        // Register a rogue export-like route with no governance entry.
        app('router')->get('api/v1/rogue/report/export.csv', fn () => 'x');

        $unregistered = app(ExportRouteDiscoveryService::class)->unregistered();
        $signatures = array_column($unregistered, 'signature');
        $this->assertContains('GET api/v1/rogue/report/export.csv', $signatures);

        $audit = app(ExportGovernanceAuditService::class)->evaluate();
        $this->assertSame(ExportGovernanceAuditService::DECISION_NO_GO, $audit['decision']);
    }

    public function test_admin_governance_endpoints_are_not_flagged_as_exports(): void
    {
        $signatures = array_column(app(ExportRouteDiscoveryService::class)->discover(), 'signature');

        $this->assertNotContains('GET api/v1/admin/report-export-metering/summary', $signatures);
        $this->assertNotContains('GET api/v1/admin/export-governance/routes', $signatures);
    }
}
