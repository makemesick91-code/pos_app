<?php

/**
 * Sprint 21 — Public Website / Landing Page Readiness Foundation.
 *
 * Governance rules for public website readiness: required public pages, required
 * public website docs, allowed lead-form CTA targets, required signoff roles,
 * blocking/watch risk severities, accepted-risk expiry requirements, and the
 * cumulative Sprint 13–20 gate commands that must remain registered. Contains no
 * secrets, no real payment gateway credentials, no server credentials, no live
 * analytics/ad pixel token, and no real tenant/customer data. Package/pricing
 * content here is governance metadata only; there is NO public self-service
 * signup and NO real billing collection in Sprint 21. Doc paths are resolved
 * relative to the repository root (base_path('..')) by the services.
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Public pages that must exist (approved/published) for a GO decision.
    'required_pages' => [
        'HOME',
        'PACKAGES',
        'PRIVACY',
        'TERMS',
        'THANK_YOU',
    ],

    // Pages whose SEO title/description are checked by SeoMetadataGovernanceService.
    'seo_required_pages' => [
        'HOME',
        'PACKAGES',
    ],

    // Non-blocking SEO readiness placeholders (documentation-level).
    'seo_readiness_placeholders' => [
        'canonical',
        'robots',
        'sitemap',
        'open_graph',
    ],

    // Public website documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/public-website/landing-page-content-map.md',
        'docs/public-website/public-website-content-governance.md',
        'docs/public-website/seo-metadata-readiness.md',
        'docs/public-website/privacy-cookie-readiness.md',
        'docs/public-website/lead-interest-policy.md',
        'docs/public-website/package-pricing-content-alignment.md',
        'docs/public-website/public-website-qa-checklist.md',
        'docs/public-website/public-website-risk-register.md',
        'docs/public-website/public-website-go-watch-no-go-report.md',
    ],

    // Privacy/cookie readiness placeholder docs.
    'cookie_policy_doc' => 'docs/public-website/privacy-cookie-readiness.md',
    'lead_policy_doc' => 'docs/public-website/lead-interest-policy.md',

    // CTA targets a landing page version is allowed to point at. Interest-only —
    // never an account-creation / signup / billing URL.
    'allowed_cta_targets' => [
        '#interest',
        '/#interest',
        '/packages',
        '/thank-you',
    ],

    // Named rate limiter for the public lead interest endpoint.
    'lead_rate_limit' => 'public-interest',

    // Signoff roles that must all approve (non-rejected) for a GO decision.
    'required_signoff_roles' => [
        'OWNER',
        'TECHNICAL',
        'SALES',
        'OPERATIONS',
        'LEGAL_PRIVACY',
    ],

    // Risk severities that force NO-GO (unless a valid accepted risk) / WATCH.
    'blocking_risk_severities' => ['CRITICAL', 'HIGH'],
    'watch_risk_severities' => ['MEDIUM'],

    // Severities whose accepted risk must carry an expiry/review date + approver.
    'accepted_risk_requires_expiry_for' => ['CRITICAL', 'HIGH', 'MEDIUM'],

    // Hard Sprint 21 guardrails.
    'live_tracking_tokens_allowed' => false,
    'public_self_service_signup_allowed' => false,
    'real_billing_collection_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Cumulative Sprint 13–20 release/pilot/handover/operations/commercial
    // commands that must remain registered for the public website gate.
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
        'commercial:launch-readiness',
        'commercial:package-summary',
        'commercial:onboarding-capacity',
        'commercial:launch-go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the public website GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'release_gate' => ['production:readiness-check', 'release:go-no-go'],
        'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
        'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
        'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
        'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
        'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
        'commercial_launch_gate' => ['commercial:launch-readiness', 'commercial:package-summary', 'commercial:onboarding-capacity', 'commercial:launch-go-no-go'],
    ],
];
