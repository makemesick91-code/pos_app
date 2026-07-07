<?php

/**
 * Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.
 *
 * Pilot deployment / field trial gate rules (no secrets, no real server
 * credentials, no real tenant/customer data here). Doc paths are resolved
 * relative to the repository root (base_path('..')) by the pilot deployment /
 * field trial services, because the Laravel app lives in backend/ while
 * docs/scripts live at the repository root.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Pilot deployment / field trial docs that must exist for a GO decision.
    'required_docs' => [
        'docs/pilot/pilot-deployment-checklist.md',
        'docs/pilot/field-trial-evidence-pack.md',
        'docs/pilot/backend-deployment-dry-run.md',
        'docs/pilot/android-rc-artifact-handling.md',
        'docs/pilot/operator-device-readiness.md',
        'docs/pilot/demo-tenant-pilot-setup-evidence.md',
        'docs/pilot/post-deploy-smoke-checklist.md',
        'docs/pilot/pilot-rollback-checklist.md',
        'docs/pilot/daily-pilot-monitoring-checklist.md',
        'docs/pilot/field-issue-register.md',
        'docs/pilot/field-trial-go-watch-no-go-report.md',
    ],

    // Sprint 13 release docs a pilot deployment also depends on.
    'required_release_docs' => [
        'docs/release/production-readiness-checklist.md',
        'docs/release/backup-restore-runbook.md',
        'docs/release/release-go-no-go-runbook.md',
    ],

    // Sprint 14 RC/UAT docs a pilot deployment also depends on.
    'required_rc_docs' => [
        'docs/pilot/pilot-rc-checklist.md',
        'docs/pilot/operator-uat-checklist.md',
        'docs/pilot/smoke-scenario-pack.md',
    ],

    // Artisan commands that must remain registered for the deployment/field gate.
    'required_commands' => [
        'production:readiness-check',
        'release:go-no-go',
        'pilot:rc-check',
        'pilot:uat-summary',
        'pilot:deployment-check',
        'pilot:field-trial-summary',
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Field-issue severities that force a NO-GO while still open.
    'blocking_issue_severities' => ['BLOCKER', 'CRITICAL'],

    // Field-issue severity that normally produces WATCH while still open.
    'watch_issue_severities' => ['MAJOR'],

    // Field-issue statuses considered "still open" for gating purposes.
    'open_issue_statuses' => ['OPEN', 'IN_PROGRESS', 'RETEST'],

    // Canonical field trial evidence categories. Keys are stable identifiers;
    // labels are what the field trial coordinator sees.
    'evidence_categories' => [
        'backend_deployment_dry_run' => 'Backend deployment dry-run',
        'android_rc_artifact' => 'Android RC artifact handling',
        'demo_tenant_setup' => 'Demo tenant pilot setup',
        'operator_device_readiness' => 'Operator device readiness',
        'post_deploy_smoke' => 'Post-deploy smoke',
        'offline_cash_field' => 'Offline cash field check',
        'qris_status_field' => 'QRIS status field check',
        'receipt_printer_field' => 'Receipt/printer field check',
        'inventory_report_closing_field' => 'Inventory/report/closing field check',
        'subscription_device_field' => 'Subscription/device gate field check',
        'rollback_readiness' => 'Rollback readiness',
        'daily_monitoring' => 'Daily monitoring readiness',
        'field_issue_register' => 'Field issue register',
    ],

    // Evidence categories that must be present for a valid field trial pack.
    'required_evidence_categories' => [
        'backend_deployment_dry_run',
        'android_rc_artifact',
        'operator_device_readiness',
        'post_deploy_smoke',
        'rollback_readiness',
        'daily_monitoring',
        'field_issue_register',
    ],

    // Optional structured field trial result file (repo-root relative). When
    // present it is parsed for issues. It must NOT contain real credentials or
    // production customer data — demo tenant / placeholder only.
    'field_trial_result_file' => 'docs/pilot/field-trial-result.json',
];
