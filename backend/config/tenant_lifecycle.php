<?php

/**
 * Sprint 25 — Tenant Lifecycle Enforcement & Manual Suspension Governance
 * Foundation.
 *
 * Governance rules for tenant lifecycle status (the single server-side source of
 * truth), platform-admin manual suspension/lift, runtime enforcement, the
 * allowlist of routes that must stay reachable while a tenant is suspended, the
 * canonical TLS-R rules registry, the hard automation guardrails, required docs,
 * and the cumulative Sprint 13–24 gate commands that must remain registered.
 *
 * Tenant lifecycle enforcement is server-side authoritative. The Android/POS
 * client is UX only and is NEVER the enforcement authority (TLS-R009). Manual
 * suspension has precedence over subscription renewal/dunning automation
 * (TLS-R004); automation can never auto-suspend or auto-reactivate a tenant.
 * Contains no secrets and no real tenant/customer data. Doc paths resolve
 * relative to the repository root (base_path('..')).
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // The canonical lifecycle status vocabulary (mirrors TenantLifecycleStatus).
    'statuses' => [
        'onboarding',
        'active',
        'grace',
        'past_due',
        'suspended',
        'cancelled',
        'archived',
    ],

    // Lifecycle statuses that deny operational (POS) access at the guard.
    'blocked_statuses' => [
        'suspended',
        'cancelled',
        'archived',
    ],

    // Reason categories offered for a manual suspension (validation allowlist).
    'suspension_reason_categories' => [
        'PAYMENT_OVERDUE',
        'ABUSE',
        'FRAUD_REVIEW',
        'SECURITY',
        'CONTRACT_TERMINATION',
        'CUSTOMER_REQUEST',
        'COMPLIANCE',
        'OTHER',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (TLS-R010).
    'rules' => [
        'TLS-R001' => 'Tenant lifecycle status must have a single server-side source of truth.',
        'TLS-R002' => 'Manual suspension may only be created/lifted by platform admin authorization.',
        'TLS-R003' => 'Suspended tenants must be blocked by server-side runtime enforcement, not by client UI only.',
        'TLS-R004' => 'Manual suspension has precedence over subscription renewal and dunning automation.',
        'TLS-R005' => 'Suspension mutation must be audit-logged with redacted metadata.',
        'TLS-R006' => 'Suspension reason is mandatory and must not contain secrets or sensitive payment credentials.',
        'TLS-R007' => 'Tenant-scoped routes must use lifecycle guard or an explicitly documented allowlist.',
        'TLS-R008' => 'Platform/admin routes, billing callbacks, and health routes must not be accidentally locked by tenant suspension.',
        'TLS-R009' => 'Android/POS client must handle suspension responses gracefully but must never be the enforcement authority.',
        'TLS-R010' => 'Sprint 25 GO requires tenant-lifecycle:go-no-go green.',
    ],

    // Routes that MUST remain reachable while a tenant is suspended (TLS-R007,
    // TLS-R008). These are technical/auth/health/status routes documented as an
    // explicit allowlist; they are NOT wrapped by the tenant.lifecycle guard.
    'enforcement_allowlist' => [
        'GET api/v1/health',
        'POST api/v1/auth/login',
        'GET api/v1/auth/me',
        'POST api/v1/auth/logout',
        'GET api/v1/tenant-context',
        'GET api/v1/subscription/status',
        'POST api/v1/devices/register',
        'POST api/v1/devices/heartbeat',
        'GET api/v1/devices',
    ],

    // Hard Sprint 25 guardrails. Every flag MUST stay false; a true value forces
    // the readiness/go-no-go decision to NO_GO.
    'real_tenant_hard_delete_allowed' => false,
    'auto_tenant_suspension_allowed' => false,
    'auto_tenant_reactivation_allowed' => false,
    'dunning_can_override_manual_suspension_allowed' => false,
    'renewal_can_override_manual_suspension_allowed' => false,
    'client_side_enforcement_authoritative' => false,
    'public_tenant_suspension_api_allowed' => false,
    'tenant_status_computed_in_controller_allowed' => false,
    'real_notification_sending_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Tenant lifecycle documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/tenant-lifecycle-governance.md',
        'docs/tenant-lifecycle/tenant-lifecycle-status-model.md',
        'docs/tenant-lifecycle/manual-suspension-governance.md',
        'docs/tenant-lifecycle/enforcement-allowlist.md',
        'docs/tenant-lifecycle/renewal-dunning-precedence.md',
        'docs/tenant-lifecycle/tenant-lifecycle-go-watch-no-go-report.md',
    ],

    // Cumulative Sprint 13–24 commands that must remain registered for the
    // tenant lifecycle gate (prior-sprint gate contract).
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
        'public-website:readiness',
        'public-website:content-summary',
        'public-website:lead-summary',
        'public-website:go-no-go',
        'sales-pipeline:readiness',
        'sales-pipeline:lead-summary',
        'sales-pipeline:activity-summary',
        'sales-pipeline:go-no-go',
        'billing-collection:readiness',
        'billing-collection:invoice-summary',
        'billing-collection:collection-summary',
        'billing-collection:go-no-go',
        'subscription-renewal:readiness',
        'subscription-renewal:candidate-summary',
        'subscription-renewal:dunning-summary',
        'subscription-renewal:go-no-go',
    ],

    // The Sprint 25 tenant lifecycle commands (self-contract surfaced in go-no-go).
    'tenant_lifecycle_commands' => [
        'tenant-lifecycle:readiness',
        'tenant-lifecycle:suspension-summary',
        'tenant-lifecycle:enforcement-audit',
        'tenant-lifecycle:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the tenant lifecycle GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'release_gate' => ['production:readiness-check', 'release:go-no-go'],
        'rc_uat_gate' => ['pilot:rc-check', 'pilot:uat-summary'],
        'deployment_field_gate' => ['pilot:deployment-check', 'pilot:field-trial-summary'],
        'monitoring_hypercare_gate' => ['pilot:daily-monitoring-check', 'pilot:health-summary', 'hypercare:issue-triage'],
        'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        'closure_handover_gate' => ['pilot:closure-check', 'production:handover-summary', 'production:signoff-summary', 'production:handover-go-no-go'],
        'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
        'commercial_launch_gate' => ['commercial:launch-readiness', 'commercial:package-summary', 'commercial:onboarding-capacity', 'commercial:launch-go-no-go'],
        'public_website_gate' => ['public-website:readiness', 'public-website:content-summary', 'public-website:lead-summary', 'public-website:go-no-go'],
        'sales_pipeline_gate' => ['sales-pipeline:readiness', 'sales-pipeline:lead-summary', 'sales-pipeline:activity-summary', 'sales-pipeline:go-no-go'],
        'billing_collection_gate' => ['billing-collection:readiness', 'billing-collection:invoice-summary', 'billing-collection:collection-summary', 'billing-collection:go-no-go'],
        'subscription_renewal_gate' => ['subscription-renewal:readiness', 'subscription-renewal:candidate-summary', 'subscription-renewal:dunning-summary', 'subscription-renewal:go-no-go'],
    ],
];
