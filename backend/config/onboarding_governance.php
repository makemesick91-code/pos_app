<?php

/**
 * Sprint 33 — Tenant Onboarding, Trial Activation & First-Branch Provisioning.
 *
 * Canonical, server-side source of truth for how a brand-new UMKM/tenant is
 * provisioned from zero: create tenant → select/resolve plan → activate trial →
 * provision first branch (store) → owner/admin → first cashier → device/register
 * setup → seed safe defaults → trial-to-paid readiness → deterministic checklist.
 *
 * Design contract (never weakened by a later sprint):
 *  - Every onboarding mutation flows through App\Services\TenantOnboarding\
 *    TenantOnboardingService and its lower-level provisioning services; no
 *    controller/command re-implements provisioning (ONB-R001, ONB-R016).
 *  - The tenant plan is resolved through the canonical Sprint 26
 *    TenantPlanResolver. A missing/unknown plan fails CLOSED — there is no
 *    silent fallback to an unlimited/free plan (ONB-R002, ONB-R003).
 *  - Onboarding is transactional and idempotent by idempotency_key: a replayed
 *    key resumes the existing run and never creates a second tenant/branch/user/
 *    register/device (ONB-R004, ONB-R005, ONB-R021).
 *  - Every provisioning step enforces the Sprint 32 EntitlementAccessService
 *    before it creates a resource; a denied step is audit-logged and leaves an
 *    auditable failed state (ONB-R013, ONB-R020, ONB-R023).
 *  - Trial activation is time-bounded and audit-logged (ONB-R007). Trial-to-paid
 *    readiness uses the Sprint 30 invoice/collection services and the Sprint 31
 *    payment-gateway services; onboarding NEVER marks an invoice paid or unlocks
 *    paid entitlement directly, and a failed/cancelled/expired payment event can
 *    never activate paid access (ONB-R014, ONB-R015, ONB-R016).
 *  - Manual suspension (Sprint 25) always wins; a paid invoice never lifts it
 *    (ONB-R017). Public self-signup mutation is disabled by default (ONB-R018).
 *  - This file holds NO secrets and NO real tenant/customer data; it is safe to
 *    commit and to grep in CI. No provisioning output (audit, command, smoke,
 *    docs, API) may leak secrets or PII (ONB-R024).
 *
 * See docs/architecture/sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning.md
 * and docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */

return [
    // ONB-R001 — platform-admin driven onboarding is enabled by default. This is
    // the governed provisioning flow; it never bypasses entitlement/billing rules.
    'enabled' => env('ONBOARDING_ENABLED', true),

    // ONB-R018 — public/self-signup MUTATION is disabled by default. Turning this
    // on is not enough on its own: a self-signup run additionally requires a
    // signed approval token (see `self_signup` below). The go-no-go gate refuses
    // GO while this default is anything other than false in config.
    'public_self_signup_mutation_enabled' => env('ONBOARDING_PUBLIC_SIGNUP_ENABLED', false),

    'self_signup' => [
        // Even when the flag above is enabled, an approved-signup run must carry a
        // signed approval token; there is no anonymous tenant creation path.
        'require_signed_approval_token' => true,
        'approval_token_ttl_minutes' => 60,
    ],

    // ONB-R007 — trial activation is only ever performed by the governed
    // TrialActivationService and is time-bounded.
    'trial' => [
        'enabled' => true,
        'default_duration_days' => 14,
        'max_duration_days' => 30,
        // ONB-R003 — allowed trial plans must be a subset of the real plan
        // catalogue (config tenant_plan.plan_keys). An unknown plan fails closed.
        'allowed_plans' => ['starter', 'growth', 'professional'],
    ],

    // ONB-R008/R009/R010/R011 — required vs optional provisioning steps.
    'provisioning' => [
        'first_branch_required' => true,
        'owner_admin_required' => true,
        'first_cashier_required' => true,
        // Device/register SETUP is prepared as a one-time hashed token; the real
        // device activates later from the Android app. We never persist a raw
        // long-lived token and never expose it in any output.
        'device_register_setup_required' => true,
        'device_setup_token_hashed_only' => true,
        'device_setup_token_ttl_minutes' => 1440,
        // ONB-R012 — default seed data is safe, deterministic, tenant-isolated and
        // idempotent. No fake production transactions unless explicitly demo.
        'seed_default_data' => true,
        'seed_demo_transactions' => false,
    ],

    // ONB-R004/R005 — idempotency is mandatory for any mutation.
    'idempotency' => [
        'required' => true,
        'key_min_length' => 8,
        'key_max_length' => 128,
    ],

    // ONB-R006/R023 — audit is mandatory; metadata is redacted before it is
    // persisted or printed anywhere.
    'audit' => [
        'required' => true,
        'redact_metadata' => true,
    ],

    // ONB-R020 — on a failed step the run rolls the failed step back and records
    // an auditable failed state; a retry is idempotent (ONB-R021).
    'rollback' => [
        'leave_auditable_failed_state' => true,
        'retry_is_idempotent' => true,
    ],

    // Run lifecycle statuses (persisted in tenant_provisioning_runs.status).
    'run_statuses' => [
        'draft', 'pending', 'provisioning', 'trial_active',
        'waiting_payment', 'paid_active', 'completed', 'failed', 'cancelled',
    ],

    // Step lifecycle statuses (persisted in tenant_provisioning_steps.status).
    'step_statuses' => ['pending', 'running', 'completed', 'skipped', 'failed'],

    // Deterministic onboarding types (tenant_provisioning_runs.onboarding_type).
    'onboarding_types' => ['platform_admin', 'approved_signup', 'import_seed', 'internal'],

    // Deterministic, ordered provisioning step keys. The checklist and the
    // orchestrator both read this list so they can never drift (ONB-R022).
    'steps' => [
        'resolve_plan',
        'create_tenant',
        'activate_trial',
        'provision_first_branch',
        'provision_owner_admin',
        'provision_first_cashier',
        'prepare_device_register',
        'seed_default_data',
        'prepare_invoice',
        'prepare_payment_intent',
        'finalize',
    ],

    // ONB-R022 — deterministic checklist reason codes (never leak PII).
    'reason_codes' => [
        'PENDING'         => 'Step has not run yet.',
        'COMPLETED'       => 'Step completed successfully.',
        'SKIPPED_CONFIG'  => 'Step skipped: not required by governance/config.',
        'SKIPPED_REQUEST' => 'Step skipped: not requested for this run.',
        'DENIED_ENTITLEMENT' => 'Step denied by the plan entitlement gate.',
        'FAILED'          => 'Step failed; run left in auditable failed state.',
        'UNKNOWN_PLAN'    => 'Requested plan could not be resolved; failing closed.',
        'TRIAL_DISABLED'  => 'Trial activation is disabled by governance.',
        'PLAN_NOT_ALLOWED_FOR_TRIAL' => 'Requested plan is not eligible for trial.',
    ],

    // Hard Sprint 33 guardrails. Every flag MUST stay false; a true value forces
    // the readiness / go-no-go decision to NO_GO.
    'unknown_plan_grants_unlimited_allowed' => false,
    'onboarding_bypasses_entitlement_service_allowed' => false,
    'onboarding_marks_invoice_paid_directly_allowed' => false,
    'failed_payment_activates_paid_access_allowed' => false,
    'paid_invoice_lifts_manual_suspension_allowed' => false,
    'public_route_can_mutate_onboarding_lifecycle_allowed' => false,
    'tenant_route_can_mutate_onboarding_lifecycle_allowed' => false,
    'raw_credential_in_output_allowed' => false,

    // The Sprint 33 onboarding commands (self-contract surfaced in go-no-go).
    'onboarding_commands' => [
        'onboarding:plan-readiness',
        'onboarding:start',
        'onboarding:checklist',
        'onboarding:trial-summary',
        'onboarding:decision-summary',
        'onboarding:governance-audit',
        'onboarding:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–32) that must remain
    // registered for the onboarding gate (prior-sprint gate contract, ONB-R026).
    'required_commands' => [
        'subscription-renewal:go-no-go',
        'tenant-lifecycle:go-no-go',
        'tenant-plan:go-no-go',
        'report-export-metering:go-no-go',
        'usage-ledger:go-no-go',
        'export-governance:go-no-go',
        'billing:go-no-go',
        'payment-gateway:go-no-go',
        'entitlement:go-no-go',
    ],

    // Required documentation contract (ONB-R026). Paths are repo-root relative.
    'required_docs' => [
        'docs/architecture/sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning.md',
        'docs/sprints/sprint-33-tenant-onboarding-trial-activation-first-branch-provisioning-evidence.md',
    ],

    // Canonical foundation rules registry. Locked by tests/gates (ONB-R024/R026).
    'rules' => [
        'ONB-R001' => 'Tenant onboarding must use the canonical TenantOnboardingService orchestrator.',
        'ONB-R002' => 'Tenant plan must resolve through the canonical TenantPlanResolver.',
        'ONB-R003' => 'A missing/unknown plan must fail closed, never fall back to unlimited/free.',
        'ONB-R004' => 'Onboarding must be transactional and idempotent.',
        'ONB-R005' => 'An onboarding mutation request must carry a unique idempotency key.',
        'ONB-R006' => 'Tenant creation must be audit-logged with redacted metadata.',
        'ONB-R007' => 'Trial activation must be time-bounded and audit-logged.',
        'ONB-R008' => 'First branch (store) provisioning is required unless disabled by governance.',
        'ONB-R009' => 'Owner/admin user provisioning is required.',
        'ONB-R010' => 'Cashier provisioning must respect the Sprint 32 user/cashier limit.',
        'ONB-R011' => 'Device/register setup must respect the Sprint 32 device/register limit.',
        'ONB-R012' => 'Default seed data must be safe, deterministic, and tenant-isolated.',
        'ONB-R013' => 'No onboarding step may bypass EntitlementAccessService.',
        'ONB-R014' => 'Trial-to-paid transition must use the Sprint 30 invoice/collection services.',
        'ONB-R015' => 'QRIS/payment-intent creation must use the Sprint 31 payment-gateway services.',
        'ONB-R016' => 'A failed/cancelled/expired payment event never activates paid entitlement.',
        'ONB-R017' => 'Manual suspension always wins over onboarding/payment state.',
        'ONB-R018' => 'Public self-signup mutation is disabled unless a signed approval/token flow governs it.',
        'ONB-R019' => 'No tenant/public route may mutate onboarding lifecycle after provisioning without a service guard.',
        'ONB-R020' => 'A provisioning failure must leave an auditable failed state.',
        'ONB-R021' => 'A retry must be idempotent and never duplicate tenant/branch/users/register/device.',
        'ONB-R022' => 'The onboarding checklist must be deterministic and explainable.',
        'ONB-R023' => 'A denied/blocked provisioning step must be audit-logged with redacted metadata.',
        'ONB-R024' => 'Command/API/admin output must not leak secrets or PII.',
        'ONB-R025' => 'Platform-admin bypass must be explicit and audited.',
        'ONB-R026' => 'Go/no-go must verify full Plan → Invoice → Payment Intent → Gateway Settlement → Collection → Entitlement Runtime Access compatibility.',
    ],
];
