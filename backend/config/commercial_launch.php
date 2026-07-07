<?php

/**
 * Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation.
 *
 * Governance rules for commercial launch readiness: required commercial docs,
 * required signoff roles, blocking/watch signoff decisions, blocking/watch risk
 * severities, accepted-risk expiry requirements, required/recommended package
 * segments, onboarding capacity placeholders, and the cumulative Sprint 13–19
 * gate commands that must remain registered. Contains no secrets, no real payment
 * gateway credentials, no server credentials, and no real tenant/customer data.
 * Pricing here is governance metadata only. Doc paths are resolved relative to the
 * repository root (base_path('..')) by the commercial services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Commercial documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/commercial/commercial-launch-checklist.md',
        'docs/commercial/saas-package-catalog.md',
        'docs/commercial/pricing-plan-governance.md',
        'docs/commercial/sales-enablement-pack.md',
        'docs/commercial/customer-onboarding-capacity.md',
        'docs/commercial/commercial-risk-register.md',
        'docs/commercial/launch-signoff.md',
        'docs/commercial/commercial-go-watch-no-go-report.md',
        'docs/commercial/no-public-signup-no-real-billing-policy.md',
    ],

    // Sales enablement docs required by SalesEnablementReadinessService.
    'sales_enablement_docs' => [
        'docs/commercial/sales-enablement-pack.md',
        'docs/commercial/pricing-plan-governance.md',
        'docs/commercial/customer-onboarding-capacity.md',
    ],

    // Onboarding capacity docs required by OnboardingCapacityService.
    'onboarding_capacity_docs' => [
        'docs/commercial/customer-onboarding-capacity.md',
    ],

    // Signoff roles that must all be present & non-rejected for a GO decision.
    'required_signoff_roles' => [
        'OWNER',
        'TECHNICAL',
        'SALES',
        'OPERATIONS',
    ],

    // Signoff decisions that force WATCH / NO-GO.
    'watch_signoff_decisions' => ['APPROVED_WITH_RISK'],
    'blocking_signoff_decisions' => ['REJECTED'],

    // Risk severities that force NO-GO (unless a valid accepted risk) / WATCH.
    'blocking_risk_severities' => ['CRITICAL', 'HIGH'],
    'watch_risk_severities' => ['MEDIUM'],

    // Severities whose accepted risk must carry an expiry/review date + approver.
    'accepted_risk_requires_expiry_for' => ['CRITICAL', 'HIGH', 'MEDIUM'],

    // At least one active package must cover each required segment.
    'required_package_segments' => [
        'GENERAL_UMKM',
    ],

    // Recommended (non-blocking) segments — a missing one is a WATCH warning.
    'recommended_package_segments' => [
        'WARUNG',
        'TOKO_KECIL',
        'KEDAI',
        'LAUNDRY',
        'RETAIL',
        'APOTEK_LIGHT',
    ],

    // Aggregate onboarding capacity placeholders (customers per week, per level).
    'onboarding_capacity' => [
        'self_guided_per_week' => 10,
        'assisted_per_week' => 5,
        'managed_per_week' => 2,
    ],

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–19 release/pilot/handover/operations commands that must
    // remain registered for the commercial launch gate.
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

    // Prior-sprint gate contract surfaced in the commercial launch GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'release_gate' => ['production:readiness-check', 'release:go-no-go'],
        'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
        'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
        'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
        'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
        'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
    ],
];
