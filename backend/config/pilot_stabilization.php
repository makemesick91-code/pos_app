<?php

/**
 * Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation.
 *
 * Governance rules for the pilot defect register, SLA breach detection,
 * accepted-risk governance, fix verification/retest, burn-down summary, and the
 * stabilization GO / WATCH / NO-GO decision. Contains no secrets, no real server
 * credentials, and no real tenant/customer data. Doc paths are resolved relative
 * to the repository root (base_path('..')) by the stabilization services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Initial SLA target (hours) per severity, used to compute sla_due_at.
    'severity_sla_hours' => [
        'BLOCKER' => 8,
        'CRITICAL' => 24,
        'MAJOR' => 72,
        'MINOR' => 168,
        'TRIVIAL' => 336,
    ],

    // Severities whose open defect (without valid accepted risk) forces NO-GO.
    'blocking_severities' => ['BLOCKER', 'CRITICAL'],

    // Severities whose open defect normally produces WATCH.
    'watch_severities' => ['MAJOR'],

    // Open severities that may still allow GO/WATCH.
    'go_allowed_open_severities' => ['MINOR', 'TRIVIAL'],

    // Severities that must record an expiry/review date when accepted as risk.
    'accepted_risk_requires_expiry_for' => ['BLOCKER', 'CRITICAL', 'MAJOR'],

    // When true, a valid unexpired accepted-risk on a blocking-severity defect
    // downgrades NO-GO to WATCH instead of forcing NO-GO. When false, blocking
    // severities remain NO-GO even when accepted.
    'accepted_risk_downgrades_blocking_to_watch' => true,

    // Statuses considered "still open" for gating/burn-down purposes.
    'open_statuses' => ['OPEN', 'IN_PROGRESS', 'FIXED', 'RETEST'],

    // Cumulative Sprint 13–17 release/pilot commands that must remain registered
    // for the stabilization gate.
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
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Stabilization docs that must exist for a GO decision.
    'required_docs' => [
        'docs/pilot/defect-register-runbook.md',
        'docs/pilot/defect-burndown-report.md',
        'docs/pilot/sla-breach-detection.md',
        'docs/pilot/accepted-risk-governance.md',
        'docs/pilot/fix-verification-retest-workflow.md',
        'docs/pilot/stabilization-go-watch-no-go-report.md',
    ],
];
