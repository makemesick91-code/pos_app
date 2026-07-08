<?php

/**
 * Sprint 26 — Tenant Plan, Feature Entitlement & Usage Limit Governance
 * Foundation.
 *
 * This file is the canonical DEFINITION of the plan catalogue, the feature
 * entitlement registry, and the usage-limit registry. The DB tables
 * (tenant_plans, plan_entitlements, plan_usage_limits) are the persisted
 * server-side source of truth and are synced FROM this definition by
 * TenantPlanRegistrar (TPE-R001). Runtime entitlement/usage decisions are always
 * computed server-side by the central services; the Android/POS client is UX
 * only and is never the enforcement authority (TPE-R002, TPE-R010).
 *
 * Tenant lifecycle enforcement (Sprint 25) always runs BEFORE entitlement/usage
 * enforcement (TPE-R004); a suspended/cancelled/archived tenant can never regain
 * access through plan assignment or entitlement override (TPE-R005). Contains no
 * secrets and no real tenant/customer data. Doc paths resolve relative to the
 * repository root (base_path('..')).
 *
 * See docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md and docs/PROJECT_RULES.md.
 */
return [
    // Canonical plan keys (source of truth ordering, lowest → highest tier).
    'plan_keys' => ['starter', 'growth', 'professional', 'enterprise'],

    // The plan applied when a tenant has no explicit active assignment. It is a
    // real, safe, RESTRICTED plan (not enterprise/unlimited) — never a bypass.
    'default_plan' => 'starter',

    // The plan auto-assigned to factory tenants in the test suite so the
    // cumulative Sprint 2–25 operational suites stay green (mirrors the Sprint 10
    // subscription/device auto-provision convention). Never used in production
    // resolution — production tenants are assigned a plan explicitly.
    'test_default_plan' => 'enterprise',

    // Feature entitlement registry. Every runtime entitlement check resolves a key
    // defined here. reports/inventory/pos keys map to real operational routes;
    // manage/billing keys are foundation registry entries for later sprints.
    'entitlements' => [
        'pos.sales' => 'Process POS sales/checkout.',
        'pos.refunds' => 'Process POS refunds.',
        'pos.discounts' => 'Apply POS discounts.',
        'inventory.basic' => 'Basic inventory (stock view, adjustments).',
        'inventory.advanced' => 'Advanced inventory (multi-store, movements analytics).',
        'reports.basic' => 'Basic reports & daily closing.',
        'reports.advanced' => 'Advanced reports & exports.',
        'users.manage' => 'Manage tenant users.',
        'devices.manage' => 'Manage registered devices.',
        'branches.manage' => 'Manage branches/stores.',
        'billing.view' => 'View billing/subscription information.',
    ],

    // Usage-limit registry. meterable=true limits are computed from real DB
    // counts by TenantUsageMeter; meterable=false are declared foundation limits
    // whose live metering is deferred and reported explicitly (never a silent 0).
    'usage_limits' => [
        'branches.max' => ['label' => 'Maximum branches/stores', 'period' => 'lifetime', 'meterable' => true],
        'users.max' => ['label' => 'Maximum tenant users', 'period' => 'lifetime', 'meterable' => true],
        'devices.max' => ['label' => 'Maximum active registered devices', 'period' => 'lifetime', 'meterable' => true],
        'products.max' => ['label' => 'Maximum products', 'period' => 'lifetime', 'meterable' => true],
        'transactions.monthly' => ['label' => 'Sales transactions per month', 'period' => 'monthly', 'meterable' => true],
        // Sprint 27 — now live: metered from the tenant usage event ledger (UEL-R006).
        'reports.exports.monthly' => ['label' => 'Report exports per month', 'period' => 'monthly', 'meterable' => true],
    ],

    // The plan matrix — canonical entitlement flags and usage limits per plan.
    // A null limit value with unlimited=false means "not configured"; unlimited
    // limits use ['unlimited' => true]. Enterprise is unlimited on every limit.
    'plans' => [
        'starter' => [
            'name' => 'Starter',
            'description' => 'Entry plan for a single small outlet.',
            'billing_interval' => 'monthly',
            'entitlements' => [
                'pos.sales' => true,
                'pos.refunds' => true,
                'pos.discounts' => true,
                'inventory.basic' => true,
                'inventory.advanced' => false,
                'reports.basic' => true,
                'reports.advanced' => false,
                'users.manage' => true,
                'devices.manage' => true,
                'branches.manage' => false,
                'billing.view' => true,
            ],
            'limits' => [
                'branches.max' => ['limit' => 1],
                'users.max' => ['limit' => 25],
                'devices.max' => ['limit' => 10],
                'products.max' => ['limit' => 200],
                'transactions.monthly' => ['limit' => 5000],
                'reports.exports.monthly' => ['limit' => 50],
            ],
        ],
        'growth' => [
            'name' => 'Growth',
            'description' => 'Growing business with multiple outlets.',
            'billing_interval' => 'monthly',
            'entitlements' => [
                'pos.sales' => true,
                'pos.refunds' => true,
                'pos.discounts' => true,
                'inventory.basic' => true,
                'inventory.advanced' => true,
                'reports.basic' => true,
                'reports.advanced' => false,
                'users.manage' => true,
                'devices.manage' => true,
                'branches.manage' => true,
                'billing.view' => true,
            ],
            'limits' => [
                'branches.max' => ['limit' => 5],
                'users.max' => ['limit' => 100],
                'devices.max' => ['limit' => 50],
                'products.max' => ['limit' => 2000],
                'transactions.monthly' => ['limit' => 50000],
                'reports.exports.monthly' => ['limit' => 500],
            ],
        ],
        'professional' => [
            'name' => 'Professional',
            'description' => 'Established multi-branch operation.',
            'billing_interval' => 'monthly',
            'entitlements' => [
                'pos.sales' => true,
                'pos.refunds' => true,
                'pos.discounts' => true,
                'inventory.basic' => true,
                'inventory.advanced' => true,
                'reports.basic' => true,
                'reports.advanced' => true,
                'users.manage' => true,
                'devices.manage' => true,
                'branches.manage' => true,
                'billing.view' => true,
            ],
            'limits' => [
                'branches.max' => ['limit' => 25],
                'users.max' => ['limit' => 500],
                'devices.max' => ['limit' => 250],
                'products.max' => ['limit' => 20000],
                'transactions.monthly' => ['limit' => 500000],
                'reports.exports.monthly' => ['limit' => 5000],
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'Unlimited enterprise plan.',
            'billing_interval' => 'monthly',
            'entitlements' => [
                'pos.sales' => true,
                'pos.refunds' => true,
                'pos.discounts' => true,
                'inventory.basic' => true,
                'inventory.advanced' => true,
                'reports.basic' => true,
                'reports.advanced' => true,
                'users.manage' => true,
                'devices.manage' => true,
                'branches.manage' => true,
                'billing.view' => true,
            ],
            'limits' => [
                'branches.max' => ['unlimited' => true],
                'users.max' => ['unlimited' => true],
                'devices.max' => ['unlimited' => true],
                'products.max' => ['unlimited' => true],
                'transactions.monthly' => ['unlimited' => true],
                'reports.exports.monthly' => ['unlimited' => true],
            ],
        ],
    ],

    // Plan assignment sources (validation allowlist).
    'assignment_sources' => ['platform_admin', 'system', 'import', 'test'],

    // Reason categories offered for an entitlement override (validation allowlist).
    'override_reason_categories' => [
        'PROMOTION',
        'PILOT',
        'CONTRACT',
        'SUPPORT',
        'INCIDENT_MITIGATION',
        'COMPLIANCE',
        'OTHER',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (TPE-R010/R011).
    'rules' => [
        'TPE-R001' => 'Tenant plan must have a single server-side source of truth.',
        'TPE-R002' => 'Feature entitlement must be enforced server-side and must not rely on Android/UI visibility.',
        'TPE-R003' => 'Usage limits must be evaluated by a central tenant usage limit service before protected mutations.',
        'TPE-R004' => 'Tenant lifecycle enforcement must run before entitlement and usage limit enforcement.',
        'TPE-R005' => 'Suspended, cancelled, or archived tenants must not regain access through plan assignment or entitlement override.',
        'TPE-R006' => 'Platform admin authorization is required for plan assignment and entitlement override mutations.',
        'TPE-R007' => 'Plan assignment and entitlement override mutations must be audit-logged with redacted metadata.',
        'TPE-R008' => 'Entitlement denied responses must use a stable machine-readable code such as FEATURE_NOT_ENTITLED.',
        'TPE-R009' => 'Usage limit exceeded responses must use a stable machine-readable code such as USAGE_LIMIT_EXCEEDED.',
        'TPE-R010' => 'Android may present entitlement/limit UX, but server-side enforcement remains authoritative.',
        'TPE-R011' => 'Sprint 26 GO requires tenant-plan:go-no-go green.',
        'TPE-R012' => 'Sprint 26 rules must coexist with Sprint 25 TLS-R001..R010 and must not weaken lifecycle suspension governance.',
    ],

    // Operational routes that MUST carry the entitlement guard, and the key each
    // requires. Audited by TenantPlanEnforcementAuditService.
    'entitlement_guarded_routes' => [
        'pos.sales' => ['api/v1/sales'],
        'inventory.basic' => ['api/v1/products', 'api/v1/inventory/current-stock'],
        'reports.basic' => ['api/v1/reports/daily-sales'],
    ],

    // Mutations that MUST carry the usage-limit guard, and the limit each meters.
    'usage_guarded_routes' => [
        'products.max' => 'POST api/v1/products',
        'transactions.monthly' => 'POST api/v1/sales',
        // Sprint 27 — report export metering (reports.exports.monthly, UEL-R006/R009).
        'reports.exports.monthly' => 'GET api/v1/reports/daily-sales/export.csv',
    ],

    // Hard Sprint 26 guardrails. Every flag MUST stay false; a true value forces
    // the readiness/go-no-go decision to NO_GO.
    'client_side_entitlement_authoritative' => false,
    'suspended_tenant_can_be_overridden_allowed' => false,
    'entitlement_computed_in_controller_allowed' => false,
    'plan_assignment_without_platform_admin_allowed' => false,
    'override_without_reason_allowed' => false,
    'real_billing_charge_on_plan_change_allowed' => false,

    // Repo-root relative Android release readiness script that must exist.
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',

    // Sprint 26 documentation that must exist for a GO decision.
    'required_docs' => [
        'docs/architecture/tenant-plan-entitlement-usage-governance.md',
        'docs/sprints/sprint-26-tenant-plan-feature-entitlement-usage-limit-governance-foundation.md',
        'docs/tenant-plan/plan-source-of-truth.md',
        'docs/tenant-plan/feature-entitlement-governance.md',
        'docs/tenant-plan/usage-limit-governance.md',
        'docs/tenant-plan/lifecycle-precedence.md',
    ],

    // The Sprint 26 tenant plan commands (self-contract surfaced in go-no-go).
    'tenant_plan_commands' => [
        'tenant-plan:readiness',
        'tenant-plan:entitlement-summary',
        'tenant-plan:usage-limit-summary',
        'tenant-plan:enforcement-audit',
        'tenant-plan:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 13–25) that must remain
    // registered for the tenant plan gate (prior-sprint gate contract).
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
        'tenant-lifecycle:readiness',
        'tenant-lifecycle:suspension-summary',
        'tenant-lifecycle:enforcement-audit',
        'tenant-lifecycle:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the tenant plan GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'release_gate' => ['production:readiness-check', 'release:go-no-go'],
        'stabilization_gate' => ['pilot:defect-summary', 'pilot:burndown-summary', 'pilot:sla-check', 'pilot:stabilization-go-no-go'],
        'operations_gate' => ['production:ops-health', 'production:incident-summary', 'production:backup-governance-check', 'production:post-handover-go-no-go'],
        'commercial_launch_gate' => ['commercial:launch-readiness', 'commercial:package-summary', 'commercial:onboarding-capacity', 'commercial:launch-go-no-go'],
        'public_website_gate' => ['public-website:readiness', 'public-website:content-summary', 'public-website:lead-summary', 'public-website:go-no-go'],
        'sales_pipeline_gate' => ['sales-pipeline:readiness', 'sales-pipeline:lead-summary', 'sales-pipeline:activity-summary', 'sales-pipeline:go-no-go'],
        'billing_collection_gate' => ['billing-collection:readiness', 'billing-collection:invoice-summary', 'billing-collection:collection-summary', 'billing-collection:go-no-go'],
        'subscription_renewal_gate' => ['subscription-renewal:readiness', 'subscription-renewal:candidate-summary', 'subscription-renewal:dunning-summary', 'subscription-renewal:go-no-go'],
        'tenant_lifecycle_gate' => ['tenant-lifecycle:readiness', 'tenant-lifecycle:suspension-summary', 'tenant-lifecycle:enforcement-audit', 'tenant-lifecycle:go-no-go'],
    ],
];
