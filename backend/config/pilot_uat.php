<?php

/**
 * Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.
 *
 * Pilot RC / operator UAT gate rules (no secrets, no real tenant data here).
 * Doc paths are resolved relative to the repository root (base_path('..')) by
 * PilotReleaseCandidateService, because the Laravel app lives in backend/ while
 * docs/scripts live at the repository root.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Pilot RC / UAT docs that must exist for a pilot RC to be GO.
    'required_docs' => [
        'docs/pilot/pilot-rc-checklist.md',
        'docs/pilot/operator-uat-checklist.md',
        'docs/pilot/smoke-scenario-pack.md',
        'docs/pilot/issue-register.md',
        'docs/pilot/uat-result-template.md',
        'docs/pilot/rc-go-watch-no-go-evidence.md',
    ],

    // Sprint 13 release docs that a pilot RC also depends on.
    'required_release_docs' => [
        'docs/release/production-readiness-checklist.md',
        'docs/release/backup-restore-runbook.md',
        'docs/release/release-go-no-go-runbook.md',
    ],

    // Artisan commands that must remain registered for the pilot gate.
    'required_commands' => [
        'production:readiness-check',
        'release:go-no-go',
        'pilot:rc-check',
        'pilot:uat-summary',
    ],

    // Issue-register severities that force a NO-GO while still open.
    'blocking_issue_severities' => ['BLOCKER', 'CRITICAL'],

    // Issue-register severity that normally produces WATCH while still open.
    'watch_issue_severities' => ['MAJOR'],

    // Issue statuses considered "still open" for gating purposes.
    'open_issue_statuses' => ['OPEN', 'IN_PROGRESS', 'RETEST'],

    // Canonical operator UAT scenarios the pilot must cover. Keys are stable
    // identifiers; labels are what operators see on the checklist.
    'scenarios' => [
        'login' => 'Login & tenant context',
        'product_sync' => 'Product sync',
        'cashier_cart' => 'Cashier cart',
        'cash_sale' => 'Cash sale',
        'qris_status' => 'QRIS payment status',
        'receipt_preview' => 'Receipt preview',
        'printer_check' => 'Printer check',
        'offline_cash_sale' => 'Offline cash sale',
        'offline_sync_retry' => 'Offline sync retry',
        'inventory_movement' => 'Inventory stock movement',
        'daily_report' => 'Daily report',
        'daily_closing' => 'Daily closing',
        'subscription_device' => 'Subscription/device gate',
        'admin_onboarding' => 'Admin onboarding/demo tenant',
        'demo_reset_guard' => 'Demo data reset guard',
    ],

    // Subset of scenarios that must be present for a valid pilot RC.
    'required_scenarios' => [
        'login',
        'product_sync',
        'cashier_cash_sale',
        'qris_status',
        'receipt_printer',
        'offline_cash_sync',
        'inventory',
        'reports_closing',
        'subscription_device',
        'admin_onboarding',
    ],

    // Optional structured UAT result file (repo-root relative). When present it
    // is parsed for scenario statuses and issues. It must NOT contain real
    // credentials or production customer data — demo tenant/placeholder only.
    'uat_result_file' => 'docs/pilot/uat-result.json',
];
