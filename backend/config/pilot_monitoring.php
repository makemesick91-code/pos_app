<?php

/**
 * Sprint 16 — Pilot Monitoring & Hypercare Foundation.
 *
 * Pilot monitoring / hypercare gate rules (no secrets, no real server
 * credentials, no real tenant/customer data here). Doc paths are resolved
 * relative to the repository root (base_path('..')) by the pilot monitoring /
 * hypercare services, because the Laravel app lives in backend/ while
 * docs/scripts live at the repository root.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Pilot monitoring / hypercare docs that must exist for a GO decision.
    'required_docs' => [
        'docs/pilot/daily-monitoring-runbook.md',
        'docs/pilot/hypercare-issue-triage-workflow.md',
        'docs/pilot/field-issue-severity-sla.md',
        'docs/pilot/operator-feedback-log.md',
        'docs/pilot/pilot-health-summary-template.md',
        'docs/pilot/hypercare-go-watch-no-go-report.md',
        'docs/pilot/failed-sync-monitoring-checklist.md',
        'docs/pilot/payment-qris-monitoring-checklist.md',
        'docs/pilot/device-subscription-anomaly-checklist.md',
        'docs/pilot/closing-report-monitoring-checklist.md',
    ],

    // Artisan commands that must remain registered for the monitoring gate
    // (cumulative Sprint 13–16 release/pilot contract).
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
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Canonical daily monitoring signals. Every listed key must be defined for
    // the daily monitoring check to report GO.
    'required_signals' => [
        'backend_health',
        'auth_login',
        'tenant_context',
        'product_sync',
        'cashier_cash_sale',
        'qris_payment_status',
        'receipt_printer',
        'offline_cash_queue',
        'offline_sync_retry',
        'inventory_movement',
        'daily_sales_report',
        'daily_closing',
        'subscription_device_status',
        'admin_onboarding',
        'demo_data_reset_guard',
    ],

    // Signals whose FAIL forces a NO-GO (core pilot flows). A FAIL on a
    // non-critical signal only downgrades to WATCH.
    'critical_signals' => [
        'backend_health',
        'auth_login',
        'tenant_context',
        'product_sync',
        'cashier_cash_sale',
        'qris_payment_status',
        'offline_cash_queue',
        'offline_sync_retry',
        'daily_sales_report',
        'daily_closing',
        'subscription_device_status',
    ],

    // Canonical pilot health areas aggregated by the health summary.
    'health_areas' => [
        'app_access' => 'App access & login',
        'product_sync' => 'Product/category sync',
        'cashier_sales' => 'Cashier sales',
        'payment_qris' => 'Payment / QRIS status',
        'offline_sync' => 'Offline cash queue & sync',
        'receipt_printer' => 'Receipt & printer',
        'inventory' => 'Inventory movement',
        'reports_closing' => 'Reports & daily closing',
        'subscription_device' => 'Subscription & device gate',
        'admin_onboarding' => 'Admin & onboarding',
        'operator_feedback' => 'Operator feedback',
        'issue_register' => 'Hypercare issue register',
    ],

    // Hypercare issue severities that force a NO-GO while still open.
    'blocking_issue_severities' => ['BLOCKER', 'CRITICAL'],

    // Hypercare issue severity that normally produces WATCH while still open.
    'watch_issue_severities' => ['MAJOR'],

    // Issue statuses considered "still open" for gating purposes.
    'open_issue_statuses' => ['OPEN', 'IN_PROGRESS', 'RETEST'],

    // Canonical hypercare severity levels (level => meaning).
    'severity_levels' => [
        'BLOCKER' => 'Pilot cannot continue; same-day action required.',
        'CRITICAL' => 'Core flow broken; urgent action required.',
        'MAJOR' => 'Workaround exists but impacts pilot; planned fix (WATCH).',
        'MINOR' => 'Low impact; track in backlog.',
        'TRIVIAL' => 'Cosmetic/docs.',
    ],

    // SLA targets (initial response) per severity — governance only.
    'sla_targets' => [
        'BLOCKER' => 'Same day',
        'CRITICAL' => 'Same day',
        'MAJOR' => '1 business day',
        'MINOR' => '2 business days',
        'TRIVIAL' => 'Backlog',
    ],

    // Optional structured monitoring result file (repo-root relative). When
    // present it is parsed for per-signal / per-area statuses. It must NOT
    // contain real credentials or production customer data — demo/placeholder
    // only.
    'monitoring_result_file' => 'docs/pilot/pilot-monitoring-result.json',

    // Optional structured hypercare issue snapshot file (repo-root relative).
    // Demo/placeholder issues only — no real credentials or customer data.
    'issue_result_file' => 'docs/pilot/hypercare-issue-result.json',
];
