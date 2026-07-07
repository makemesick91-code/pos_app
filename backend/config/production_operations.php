<?php

/**
 * Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.
 *
 * Governance rules for the production operations baseline: required operations
 * documentation, incident SLA targets by severity, blocking/watch incident
 * severities, accepted-risk expiry requirements, the required production health
 * signals, and high-risk maintenance levels. Contains no secrets, no real server
 * credentials, and no real tenant/customer data. Doc paths are resolved relative
 * to the repository root (base_path('..')) by the operations services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Operations documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/operations/production-operations-runbook.md',
        'docs/operations/incident-response-runbook.md',
        'docs/operations/backup-restore-governance.md',
        'docs/operations/support-sla-operations.md',
        'docs/operations/maintenance-window-governance.md',
        'docs/operations/release-rollback-governance.md',
        'docs/operations/production-health-signals.md',
        'docs/operations/post-handover-governance-report.md',
        'docs/operations/production-operations-go-watch-no-go.md',
    ],

    // Incident SLA response/resolution targets in hours, keyed by severity.
    'incident_sla_hours' => [
        'P0' => 4,
        'P1' => 8,
        'P2' => 24,
        'P3' => 72,
        'P4' => 168,
    ],

    // Open incident severities that force NO_GO (unless a valid accepted risk).
    'blocking_incident_severities' => ['P0', 'P1'],

    // Open incident severities that force WATCH.
    'watch_incident_severities' => ['P2'],

    // Open incident severities that are acceptable for a GO decision.
    'go_allowed_open_incident_severities' => ['P3', 'P4'],

    // Severities whose accepted risk must carry an expiry/review date.
    'accepted_risk_requires_expiry_for' => ['P0', 'P1', 'P2'],

    // Production health signals evaluated by ProductionOperationsHealthService.
    'required_health_signals' => [
        'backend_health',
        'auth_login',
        'tenant_context',
        'product_sync',
        'cashier_cash_sale',
        'qris_payment_status',
        'offline_sync_queue',
        'receipt_printer',
        'inventory_movement',
        'reports_closing',
        'subscription_device',
        'admin_onboarding',
        'backup_restore_readiness',
        'support_sla_readiness',
        'release_rollback_readiness',
    ],

    // Health signals treated as critical — a FAIL forces NO_GO.
    'critical_health_signals' => [
        'backend_health',
        'auth_login',
        'tenant_context',
        'backup_restore_readiness',
    ],

    // Maintenance risk levels that require a rollback plan reference.
    'high_risk_maintenance_levels' => ['HIGH', 'CRITICAL'],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–19 release/pilot/handover/operations commands that
    // must remain registered for the production operations post-handover gate.
    'required_commands' => [
        'production:readiness-check',
        'release:go-no-go',
        'pilot:rc-check',
        'pilot:uat-summary',
        'pilot:deployment-check',
        'pilot:field-trial-summary',
        'pilot:daily-monitoring-check',
        'pilot:health-summary',
        'hypercare:issue-triage',
        'pilot:defect-summary',
        'pilot:burndown-summary',
        'pilot:sla-check',
        'pilot:stabilization-go-no-go',
        'pilot:closure-check',
        'production:handover-summary',
        'production:signoff-summary',
        'production:handover-go-no-go',
        'production:ops-health',
        'production:incident-summary',
        'production:backup-governance-check',
        'production:post-handover-go-no-go',
    ],

    // Backup/restore governance evidence keys that the runbook must document.
    'backup_restore_required_sections' => [
        'backup ownership',
        'backup frequency',
        'restore rehearsal',
        'rollback',
        'backup verification',
    ],

    // Release/rollback governance evidence keys that the runbook must document.
    'release_rollback_required_sections' => [
        'release candidate',
        'release owner',
        'rollback owner',
        'rollback checklist',
        'validation after rollback',
    ],
];
