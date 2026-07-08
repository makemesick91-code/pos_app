<?php

/**
 * Sprint 27 — Report Export Metering & Usage Event Ledger Governance Foundation.
 *
 * Canonical governance definition for the tenant usage event ledger and report
 * export metering. The tenant_usage_events table is the append-only, server-side
 * source of truth (UEL-R001/R002); monthly meters are derived from it with a
 * stable server-side period key (UEL-R005). Report export metering closes the
 * Sprint 26 deferred `reports.exports.monthly` meter (UEL-R006). Server-side
 * enforcement is authoritative; the Android/POS client is UX only (UEL-R012).
 * Tenant lifecycle enforcement (Sprint 25) always runs before entitlement/usage
 * enforcement (UEL-R009). Contains no secrets and no real tenant/customer data.
 * Doc paths resolve relative to the repository root (base_path('..')).
 *
 * See docs/architecture/report-export-metering-usage-event-ledger-governance.md
 * and docs/PROJECT_RULES.md.
 */
return [
    // Canonical meter key report export metering consumes (UEL-R006).
    'report_export_meter_key' => 'reports.exports.monthly',

    // Canonical event taxonomy for a report export usage event.
    'report_export_event_key' => 'report.exported',
    'report_export_event_category' => 'report_export',

    // Valid usage event sources (validation allowlist).
    'sources' => ['api', 'web', 'system'],

    // Report export routes that MUST carry lifecycle + entitlement + usage guards
    // (audited by ReportExportMeteringEnforcementAuditService). Format:
    // 'METHOD uri' => ['feature' => <entitlement>, 'limit' => <meter key>].
    'report_export_guarded_routes' => [
        'GET api/v1/reports/daily-sales/export.csv' => [
            'feature' => 'reports.basic',
            'limit' => 'reports.exports.monthly',
        ],
    ],

    // Canonical foundation rules registry. Locked by tests/gates (UEL-R014/R015)
    // and mirrored in docs/PROJECT_RULES.md.
    'rules' => [
        'UEL-R001' => 'Tenant usage events must be recorded in a server-side usage event ledger.',
        'UEL-R002' => 'Usage event ledger entries must be append-only by default and must not be mutated by normal runtime flows.',
        'UEL-R003' => 'Usage event metadata must be sanitized and must not store secrets, credentials, tokens, or excessive PII.',
        'UEL-R004' => 'Usage event recording must be idempotent to prevent double counting during retries.',
        'UEL-R005' => 'Monthly usage meters must use a stable server-side period key.',
        'UEL-R006' => 'Report export metering must use reports.exports.monthly as the canonical meter key.',
        'UEL-R007' => 'Successful report exports must record exactly one usage event unless an idempotent duplicate is detected.',
        'UEL-R008' => 'Blocked or failed report exports must not increment usage.',
        'UEL-R009' => 'Report export routes must run tenant lifecycle enforcement before entitlement and usage limit enforcement.',
        'UEL-R010' => 'Report export routes must require report entitlement before usage limit consumption.',
        'UEL-R011' => 'Report export usage limit exceeded responses must use stable code USAGE_LIMIT_EXCEEDED.',
        'UEL-R012' => 'Android may present report export limit UX, but server-side enforcement remains authoritative.',
        'UEL-R013' => 'Platform admin may inspect usage event summaries, but normal runtime must not expose cross-tenant usage events.',
        'UEL-R014' => 'Sprint 27 GO requires report-export-metering:go-no-go green.',
        'UEL-R015' => 'Sprint 27 rules must coexist with Sprint 25 TLS-R001..R010 and Sprint 26 TPE-R001..R012.',
    ],

    // Hard Sprint 27 guardrails. Every flag MUST stay false; a true value forces
    // the readiness/go-no-go decision to NO_GO.
    'usage_ledger_mutable_in_runtime_allowed' => false,
    'client_side_report_export_authoritative' => false,
    'usage_event_metadata_may_store_secrets_allowed' => false,
    'failed_export_counts_usage_allowed' => false,
    'cross_tenant_usage_events_in_runtime_allowed' => false,

    // The Sprint 27 commands (self-contract surfaced in go-no-go).
    'usage_event_ledger_commands' => [
        'usage-event-ledger:readiness',
        'usage-event-ledger:summary',
        'report-export-metering:summary',
        'report-export-metering:enforcement-audit',
        'report-export-metering:go-no-go',
    ],

    // Sprint 24–26 gate commands that must remain registered and green
    // (prior-sprint gate contract).
    'prior_sprint_gates' => [
        'subscription_renewal_gate' => ['subscription-renewal:readiness', 'subscription-renewal:go-no-go'],
        'tenant_lifecycle_gate' => ['tenant-lifecycle:readiness', 'tenant-lifecycle:enforcement-audit', 'tenant-lifecycle:go-no-go'],
        'tenant_plan_gate' => ['tenant-plan:readiness', 'tenant-plan:enforcement-audit', 'tenant-plan:go-no-go'],
    ],

    // Sprint 27 documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/report-export-metering-usage-event-ledger-governance.md',
        'docs/sprints/sprint-27-report-export-metering-usage-event-ledger-governance-foundation.md',
    ],
];
