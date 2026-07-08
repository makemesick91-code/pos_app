<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Services\ExportGovernance\ExportGovernanceCoverageService;
use App\Services\ExportGovernance\ExportRouteDiscoveryService;
use App\Services\ExportGovernance\ExportRouteRegistry;

/**
 * Sprint 29 — platform-admin read-only export governance visibility (EGC-R011).
 * Shows the registered/metered/exempt export routes, discovered export-like route
 * coverage, and the current reports.exports.monthly meter status. Read-only: it
 * describes route governance, never tenant usage, and exposes NO mutation or
 * metering-bypass route (EGC-R012). Behind platform.admin.
 */
class AdminExportGovernanceController extends Controller
{
    public function __construct(
        private readonly ExportRouteRegistry $registry,
        private readonly ExportRouteDiscoveryService $discovery,
        private readonly ExportGovernanceCoverageService $coverage,
    ) {}

    /**
     * GET /api/v1/admin/export-governance/routes — registered + discovered routes.
     *
     * @return array<string, mixed>
     */
    public function routes(): array
    {
        $registered = [];
        foreach ($this->registry->all() as $signature => $meta) {
            $registered[] = [
                'signature' => $signature,
                'disposition' => $meta['disposition'] ?? null,
                'report_type' => $meta['report_type'] ?? null,
                'format' => $meta['format'] ?? null,
                'entitlement' => $meta['entitlement'] ?? null,
                'meter_key' => $meta['meter_key'] ?? null,
                'metering_enabled' => (bool) ($meta['metering_enabled'] ?? false),
                'exempt_reason' => $meta['exempt_reason'] ?? null,
            ];
        }

        return [
            'data' => [
                'registered_routes' => $registered,
                'discovered_routes' => $this->discovery->discover(),
            ],
        ];
    }

    /**
     * GET /api/v1/admin/export-governance/coverage-summary.
     *
     * @return array<string, mixed>
     */
    public function coverageSummary(): array
    {
        return ['data' => $this->coverage->summary()];
    }

    /**
     * GET /api/v1/admin/export-governance/metering-summary — meter status + gaps.
     *
     * @return array<string, mixed>
     */
    public function meteringSummary(): array
    {
        $summary = $this->coverage->summary();

        return [
            'data' => [
                'meter_key' => $summary['meter_key'],
                'event_key' => $summary['event_key'],
                'event_category' => $summary['event_category'],
                'meterable' => $summary['meterable'],
                'metered_routes' => $summary['metered_routes'],
                'gaps' => $summary['gaps'],
            ],
        ];
    }
}
