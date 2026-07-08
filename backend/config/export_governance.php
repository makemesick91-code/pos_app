<?php

use App\Http\Controllers\Api\V1\ReceiptController;
use App\Http\Controllers\Api\V1\Reports\DailySalesCsvExportController;
use App\Http\Controllers\Api\V1\Reports\DailySalesReportController;
use App\Http\Controllers\Api\V1\Reports\InventoryMovementSummaryController;
use App\Http\Controllers\Api\V1\Reports\PaymentSummaryReportController;

/**
 * Sprint 29 — Multi-Export Route Metering Coverage & Export Governance Expansions.
 *
 * Canonical, server-side registry for every export-like route in the application.
 * It expands the Sprint 27 report-export metering (which only metered the
 * daily-sales CSV export) into a governed coverage surface: every route that
 * produces a downloadable/export artifact must be registered here as `metered`
 * or `exempt` with a reason (EGC-R001). Metered export routes must run tenant
 * lifecycle enforcement before entitlement and usage-limit enforcement
 * (EGC-R003/R004), consume the canonical `reports.exports.monthly` meter
 * (EGC-R005), and record exactly one `report.exported` usage event only after a
 * successful export (EGC-R006/R007). Blocked/failed exports never count
 * (EGC-R007) and metering is idempotent (EGC-R008). Server-side enforcement is
 * authoritative; the Android/POS client is UX only. Contains no secrets and no
 * real tenant/customer data. Doc paths resolve relative to the repository root.
 *
 * See docs/architecture/multi-export-route-metering-export-governance.md and
 * docs/PROJECT_RULES.md.
 */
return [
    // Canonical export taxonomy (must match usage_event_ledger.php — EGC-R005/R013).
    'meter_key' => 'reports.exports.monthly',
    'event_key' => 'report.exported',
    'event_category' => 'report_export',

    // Server-side export-like route discovery (EGC-R002). A route is export-like
    // when its URI ends with a known export file extension OR any dot/slash path
    // segment is exactly an export/download token. Hyphenated segments (e.g.
    // `report-export-metering`, `export-governance`) are NOT split, so admin
    // governance/summary endpoints are not mistaken for file exports. Anything the
    // scanner flags MUST be registered (metered or exempt) or the audit fails.
    'discovery' => [
        'export_extensions' => ['.csv', '.xlsx', '.xls', '.pdf'],
        'export_segments' => ['export', 'exports', 'download', 'downloads'],
        // Read-only governance/admin summary endpoints that mention export/metering
        // but do not themselves produce an export artifact — never flagged.
        'ignore_signatures' => [
            'GET api/v1/admin/report-export-metering/summary',
            'GET api/v1/admin/export-governance/routes',
            'GET api/v1/admin/export-governance/coverage-summary',
            'GET api/v1/admin/export-governance/metering-summary',
        ],
    ],

    // The canonical export route registry. Keyed by "METHOD uri". Every
    // export-like route the scanner discovers must appear here. `metered` routes
    // are guarded + metered; `exempt` routes are intentionally not metered and
    // MUST carry an explicit exempt_reason (EGC-R001/R010).
    'routes' => [
        // --- Metered exports -------------------------------------------------
        'GET api/v1/reports/daily-sales/export.csv' => [
            'disposition' => 'metered',
            'controller' => DailySalesCsvExportController::class.'@index',
            'report_type' => 'daily-sales',
            'format' => 'csv',
            'entitlement' => 'reports.basic',
            'meter_key' => 'reports.exports.monthly',
            'event_key' => 'report.exported',
            'event_category' => 'report_export',
            'idempotency_strategy' => 'idempotency_header_or_tenant_route_user_filters_minute_fingerprint',
            'lifecycle_required' => true,
            'entitlement_required' => true,
            'usage_limit_required' => true,
            'metering_enabled' => true,
            'metadata_sanitized' => true,
            'notes' => 'Sprint 27 daily-sales CSV export — the canonical metered export surface.',
        ],

        // --- Explicit exemptions (documented, visible in governance summary) --
        // These are report/receipt VIEWS, not downloadable export artifacts. They
        // are covered by the reports.basic entitlement + tenant lifecycle guard,
        // but intentionally do NOT consume the reports.exports.monthly quota. They
        // are declared here so governance is explicit and a future scan can never
        // silently treat them as unmetered exports (EGC-R010).
        'GET api/v1/sales/{sale}/receipt' => [
            'disposition' => 'exempt',
            'controller' => ReceiptController::class.'@show',
            'report_type' => 'per-sale-receipt',
            'format' => 'json',
            'metering_enabled' => false,
            'exempt_reason' => 'Per-sale POS receipt view returns backend-authoritative JSON receipt data for a single sale; it is an operational point-of-sale action, not a downloadable monthly report export artifact, so it is intentionally not counted against reports.exports.monthly.',
        ],
        'GET api/v1/reports/daily-sales' => [
            'disposition' => 'exempt',
            'controller' => DailySalesReportController::class.'@index',
            'report_type' => 'daily-sales',
            'format' => 'json',
            'metering_enabled' => false,
            'exempt_reason' => 'Read-only daily-sales JSON report view; it renders backend-authoritative figures on screen and does not produce a downloadable export artifact, so it is not counted against reports.exports.monthly.',
        ],
        'GET api/v1/reports/payment-summary' => [
            'disposition' => 'exempt',
            'controller' => PaymentSummaryReportController::class.'@index',
            'report_type' => 'payment-summary',
            'format' => 'json',
            'metering_enabled' => false,
            'exempt_reason' => 'Read-only payment-summary JSON report view; no downloadable export artifact is produced, so it is not counted against reports.exports.monthly.',
        ],
        'GET api/v1/reports/inventory-movements-summary' => [
            'disposition' => 'exempt',
            'controller' => InventoryMovementSummaryController::class.'@index',
            'report_type' => 'inventory-movements-summary',
            'format' => 'json',
            'metering_enabled' => false,
            'exempt_reason' => 'Read-only inventory-movements-summary JSON report view; no downloadable export artifact is produced, so it is not counted against reports.exports.monthly.',
        ],
    ],

    // Canonical foundation rules registry. Locked by tests/gates (EGC-R014/R015)
    // and mirrored in docs/PROJECT_RULES.md.
    'rules' => [
        'EGC-R001' => 'Every export-like route must be registered in the canonical export governance registry or explicitly exempted with a reason.',
        'EGC-R002' => 'Export route discovery must be server-side and must be checked by governance commands.',
        'EGC-R003' => 'Metered export routes must run tenant lifecycle enforcement before entitlement and usage limit enforcement.',
        'EGC-R004' => 'Metered export routes must require a report/export entitlement before usage limit consumption.',
        'EGC-R005' => 'Metered export routes must use reports.exports.monthly as the canonical meter key unless explicitly exempted.',
        'EGC-R006' => 'Metered export routes must record report.exported usage events only after successful export.',
        'EGC-R007' => 'Blocked or failed export routes must not increment usage.',
        'EGC-R008' => 'Export metering must be idempotent and must prevent double counting during retries.',
        'EGC-R009' => 'Export metering metadata must be sanitized and must not store secrets, credentials, tokens, or excessive PII.',
        'EGC-R010' => 'Export exemptions must be explicit, documented, and visible in governance summary.',
        'EGC-R011' => 'Platform admin may inspect export governance coverage, but normal tenants must not see cross-tenant governance data.',
        'EGC-R012' => 'Export governance must not expose runtime bypass routes for metering, usage limits, or usage ledger mutation.',
        'EGC-R013' => 'reports.exports.monthly must remain meterable from the usage event ledger after Sprint 29.',
        'EGC-R014' => 'Sprint 29 GO requires export-governance:go-no-go green.',
        'EGC-R015' => 'Sprint 29 rules must coexist with Sprint 25 TLS-R001..TLS-R010, Sprint 26 TPE-R001..TPE-R012, Sprint 27 UEL-R001..UEL-R015, and Sprint 28 ULR-R001..ULR-R016.',
    ],

    // Hard Sprint 29 guardrails. Every flag MUST stay false; a true value forces
    // the metering-audit/go-no-go decision to NO_GO.
    'export_metering_bypass_route_allowed' => false,
    'unregistered_export_route_allowed' => false,
    'export_exemption_without_reason_allowed' => false,
    'client_side_export_authoritative' => false,
    'blocked_export_counts_usage_allowed' => false,

    // The Sprint 29 commands (self-contract surfaced in go-no-go).
    'export_governance_commands' => [
        'export-governance:route-scan',
        'export-governance:coverage-summary',
        'export-governance:metering-audit',
        'export-governance:go-no-go',
    ],

    // Sprint 25–28 gate commands that must remain registered and green
    // (prior-sprint gate contract — EGC-R015).
    'prior_sprint_gates' => [
        'tenant_lifecycle_gate' => ['tenant-lifecycle:go-no-go'],
        'tenant_plan_gate' => ['tenant-plan:go-no-go'],
        'report_export_metering_gate' => ['report-export-metering:go-no-go'],
        'usage_ledger_gate' => ['usage-ledger:go-no-go'],
    ],

    // Sprint 29 documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/multi-export-route-metering-export-governance.md',
        'docs/sprints/sprint-29-multi-export-route-metering-coverage-export-governance-expansions.md',
    ],
];
