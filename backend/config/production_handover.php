<?php

/**
 * Sprint 18 — Pilot Closure & Production Handover Foundation.
 *
 * Governance rules for pilot closure, the production handover package, sign-off
 * roles/decisions, and the production handover GO/WATCH/NO_GO decision. Contains
 * no secrets, no real server credentials, and no real tenant/customer data. Doc
 * paths are resolved relative to the repository root (base_path('..')) by the
 * handover services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Closure/handover documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/handover/pilot-closure-checklist.md',
        'docs/handover/production-handover-pack.md',
        'docs/handover/operator-admin-handover.md',
        'docs/handover/final-defect-closure-summary.md',
        'docs/handover/accepted-risk-final-review.md',
        'docs/handover/production-readiness-signoff.md',
        'docs/handover/backup-restore-handover.md',
        'docs/handover/support-sla-handover.md',
        'docs/handover/release-ownership-matrix.md',
        'docs/handover/production-go-watch-no-go-report.md',
    ],

    // Sign-off roles that must approve before a production handover is GO.
    'required_signoff_roles' => [
        'OWNER',
        'TECHNICAL',
        'SUPPORT',
    ],

    // Sign-off decisions that force WATCH.
    'watch_signoff_decisions' => ['APPROVED_WITH_RISK'],

    // Sign-off decisions that force NO_GO.
    'blocking_signoff_decisions' => ['REJECTED'],

    // Defect severities whose open (un-accepted) defect forces NO_GO.
    'blocking_defect_severities' => ['BLOCKER', 'CRITICAL'],

    // Defect severities whose open defect forces WATCH.
    'watch_defect_severities' => ['MAJOR'],

    // Cumulative Sprint 13–18 release/pilot/handover commands that must remain
    // registered for the production handover gate.
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
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',
];
