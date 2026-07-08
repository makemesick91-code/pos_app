<?php

/**
 * Sprint 32 — Plan Entitlement Runtime Enforcement & Subscription Access Control.
 *
 * Canonical, server-side source of truth for RUNTIME entitlement enforcement.
 * Sprint 26 (config/tenant_plan.php) defines WHAT a plan grants (feature flags and
 * usage limits). Sprint 32 defines HOW those grants — combined with the tenant's
 * live billing/subscription/lifecycle state — are enforced at runtime before a
 * resource is created or a premium/export/report action is performed.
 *
 * Design contract (never weakened by later sprints):
 *  - Runtime enforcement is ON by default (ENT-R001/R003). There is NO silent
 *    fallback to "unlimited" — a missing/unknown plan fails CLOSED (ENT-R002).
 *  - The single runtime gate is App\Services\Entitlements\EntitlementAccessService;
 *    controllers/middleware call it, never re-implement the decision (ENT-R003).
 *  - Manual suspension (Sprint 25) always wins over any paid/active billing state
 *    (ENT-R013). A paid invoice (Sprint 30/31) never auto-lifts manual suspension
 *    (ENT-R014). Failed/expired/cancelled provider events (Sprint 31) never unlock
 *    entitlements — settlement only flows through the trusted collection layer.
 *  - Every denied (and important degraded/bypassed) decision is audit-logged with
 *    REDACTED metadata (ENT-R018). This file holds NO secrets and NO real tenant
 *    or customer data; it is safe to commit and to grep in CI (ENT-R020).
 *
 * See docs/architecture/sprint-32-plan-entitlement-runtime-enforcement-subscription-access-control.md
 * and docs/PROJECT_RULES.md. Doc paths resolve relative to the repository root.
 */

use App\Models\TenantBillingInvoice;

return [
    // ENT-R001 — runtime enforcement is enabled by default. A deployment may not
    // disable it silently; the go-no-go gate refuses NO_GO unless this is true.
    'runtime_enforcement_enabled' => env('ENTITLEMENT_RUNTIME_ENFORCEMENT', true),

    // ENT-R002 — a missing/unknown plan must fail CLOSED. When true (locked),
    // an unresolved plan denies new resource creation instead of allowing it as
    // "unlimited". There is no config path that turns unknown-plan into unlimited.
    'fail_closed_on_unknown_plan' => true,

    // ENT-R021 — entitlement decisions are recomputed per request from the live
    // DB state; there is no privilege-escalating cache. This flag exists so the
    // governance audit can assert no stale-cache bypass was introduced.
    'decision_cache_enabled' => false,

    // Billing / subscription access posture. These map a resolved billing state
    // to what the tenant may do. Reads of existing data are never blocked by the
    // write gate (ENT-R017) — existing data stays readable unless a stricter
    // suspended-read policy is explicitly enabled below.
    'access' => [
        // Trial tenants get full plan writes until the trial expires (ENT-R015).
        'trial_allows_writes' => true,
        // An expired trial is read-only (writes blocked, reads allowed) unless
        // block_reads_when_expired is enabled (ENT-R016).
        'expired_trial_read_only' => true,
        // Unpaid but still within grace: writes allowed but flagged degraded so
        // the decision is audited (ENT-R011). Never silently extends grace.
        'unpaid_within_grace_allows_writes' => true,
        'audit_degraded_writes' => true,
        // Unpaid past grace: writes blocked, reads allowed (read-only) (ENT-R012).
        'unpaid_past_grace_read_only' => true,
        // Manual suspension: writes always blocked (ENT-R013). Reads remain
        // allowed by default so the tenant can see its data / billing state; set
        // block_reads_when_suspended=true for a hard read block.
        'block_reads_when_suspended' => false,
        'block_reads_when_expired' => false,
    ],

    // Grace window (days past an invoice due date) before an unpaid tenant is
    // treated as past-grace. Never a silent unlimited extension.
    'grace' => [
        'unpaid_invoice_days' => 7,
    ],

    // The invoice collection states that count as "unpaid / outstanding" when a
    // billing access state is resolved. Paid/void/written-off are not counted.
    'outstanding_collection_states' => [
        TenantBillingInvoice::COLLECTION_PENDING,
        TenantBillingInvoice::COLLECTION_OVERDUE,
        TenantBillingInvoice::COLLECTION_FAILED,
    ],

    // ENT-R022 — platform admins may bypass tenant entitlement enforcement (they
    // carry no tenant context), but the bypass of a guarded action is still
    // audited. Off by default: platform admins simply operate cross-tenant.
    'audit_platform_admin_bypass' => true,

    // Limit keys enforced at runtime, mapped to the Sprint 26 usage-limit key and
    // the concrete resource/action they guard. branches/outlets/registers map to
    // the tenant Store model; users/cashiers to the User model; devices to the
    // active RegisteredDevice count. reports.exports.monthly is metered from the
    // Sprint 27 usage-event ledger.
    'limits' => [
        'branch'   => ['limit_key' => 'branches.max', 'resource' => 'branch',   'action' => 'create'],
        'outlet'   => ['limit_key' => 'branches.max', 'resource' => 'outlet',   'action' => 'create'],
        'register' => ['limit_key' => 'branches.max', 'resource' => 'register', 'action' => 'create'],
        'user'     => ['limit_key' => 'users.max',    'resource' => 'user',     'action' => 'create'],
        'cashier'  => ['limit_key' => 'users.max',    'resource' => 'cashier',  'action' => 'create'],
        'device'   => ['limit_key' => 'devices.max',  'resource' => 'device',   'action' => 'register'],
    ],

    // Premium feature keys (a subset of config/tenant_plan.php entitlements) that
    // Sprint 32 treats as premium/guarded surfaces at runtime. Every key MUST
    // exist in the Sprint 26 entitlement registry (governance audit asserts this).
    'feature_keys' => [
        'inventory.advanced',
        'reports.advanced',
        'branches.manage',
    ],

    // Export/report entitlement keys. An export/report action resolves the
    // feature entitlement key it requires; a tenant whose plan does not grant it
    // is denied EXPORT_NOT_IN_PLAN / REPORT_NOT_IN_PLAN (ENT-R010).
    'exports' => [
        // The daily-sales CSV export lives in the reports.basic route group, so it
        // is entitled by reports.basic (a plan without reports.basic is denied
        // EXPORT_NOT_IN_PLAN) and additionally metered by reports.exports.monthly.
        // reports.advanced is reserved for premium exports added in later sprints.
        'reports.daily-sales.csv' => ['entitlement' => 'reports.basic', 'limit_key' => 'reports.exports.monthly'],
    ],
    'reports' => [
        'reports.daily-sales'      => ['entitlement' => 'reports.basic'],
        'reports.payment-summary'  => ['entitlement' => 'reports.basic'],
        'reports.advanced'         => ['entitlement' => 'reports.advanced'],
    ],

    // Deterministic reason codes (ENT-R019). Every runtime decision resolves to
    // exactly one of these; they are stable, machine-readable, and never leak PII.
    'reason_codes' => [
        // allowed / degraded
        'ALLOWED_ACTIVE_PAID'    => 'Tenant is active/paid; action allowed.',
        'ALLOWED_ACTIVE_TRIAL'   => 'Tenant is on an active trial; action allowed.',
        'ALLOWED_WITHIN_GRACE'   => 'Tenant is unpaid but within grace; action allowed (degraded).',
        'ALLOWED_READ'           => 'Read of existing data is allowed.',
        'ALLOWED_PLATFORM_ADMIN' => 'Platform admin operates without tenant entitlement enforcement.',
        // denials
        'MANUALLY_SUSPENDED'     => 'Tenant is manually suspended; writes blocked.',
        'SUBSCRIPTION_CANCELLED' => 'Tenant subscription is cancelled.',
        'TRIAL_EXPIRED'          => 'Tenant trial has expired.',
        'UNPAID_PAST_GRACE'      => 'Tenant is unpaid past the grace period; read-only.',
        'READ_ONLY'              => 'Tenant is read-only; write blocked.',
        'OVER_QUOTA'             => 'Plan usage limit reached for this resource.',
        'USAGE_LIMIT_EXCEEDED'   => 'Plan usage limit exceeded.',
        'FEATURE_NOT_IN_PLAN'    => 'Feature is not entitled on the tenant plan.',
        'EXPORT_NOT_IN_PLAN'     => 'Export is not entitled on the tenant plan.',
        'REPORT_NOT_IN_PLAN'     => 'Report is not entitled on the tenant plan.',
        'UNKNOWN_PLAN'           => 'Tenant plan could not be resolved; failing closed.',
        'MISSING_SUBSCRIPTION'   => 'Tenant has no active subscription.',
    ],

    // Decision statuses persisted in tenant_entitlement_decisions.decision.
    'decision_statuses' => ['allowed', 'denied', 'degraded', 'read_only', 'bypassed'],

    // Which decisions to persist. Denied/degraded/read_only/bypassed are always
    // audited (ENT-R018); routine allowed reads are NOT persisted to avoid DB spam.
    'persist_decisions' => ['denied', 'degraded', 'read_only', 'bypassed'],

    // Hard Sprint 32 guardrails. Every flag MUST stay false; a true value forces
    // the readiness / go-no-go decision to NO_GO.
    'unknown_plan_grants_unlimited_allowed' => false,
    'paid_invoice_lifts_manual_suspension_allowed' => false,
    'failed_event_unlocks_entitlement_allowed' => false,
    'tenant_route_can_mutate_entitlement_state_allowed' => false,
    'silent_bypass_when_over_quota_allowed' => false,
    'denied_access_without_audit_allowed' => false,

    // Canonical foundation rules registry. Locked by tests/gates (ENT-R023/R024).
    'rules' => [
        'ENT-R001' => 'Tenant plan must resolve through the canonical TenantPlanResolver; runtime enforcement is enabled by default.',
        'ENT-R002' => 'A missing or unknown plan must fail closed, never fall back to unlimited access.',
        'ENT-R003' => 'Runtime entitlement checks must go through EntitlementAccessService, not ad-hoc controller logic.',
        'ENT-R004' => 'Branch creation must enforce the plan branch limit.',
        'ENT-R005' => 'User creation/invitation must enforce the plan user limit.',
        'ENT-R006' => 'Cashier/operator creation or cashier role assignment must enforce the cashier limit.',
        'ENT-R007' => 'Device registration/activation must enforce the plan device limit.',
        'ENT-R008' => 'Outlet/register creation must enforce the outlet/register limit.',
        'ENT-R009' => 'Premium feature routes/actions must enforce feature entitlement.',
        'ENT-R010' => 'Export/report routes/actions must enforce export/report entitlement.',
        'ENT-R011' => 'Unpaid tenants within grace may use allowed degraded access only, never a silent grace extension.',
        'ENT-R012' => 'Unpaid tenants past grace must be blocked or read-only per governance.',
        'ENT-R013' => 'Manual suspension always wins over payment/billing status.',
        'ENT-R014' => 'A paid invoice never automatically lifts a manual tenant suspension.',
        'ENT-R015' => 'Trial tenants follow trial-specific entitlements and expiry rules.',
        'ENT-R016' => 'Expired trials must be blocked or read-only per governance.',
        'ENT-R017' => 'Over-quota tenants must be denied new resource creation but existing data remains readable unless a suspended policy says otherwise.',
        'ENT-R018' => 'Denied entitlement access must be audit-logged with redacted metadata.',
        'ENT-R019' => 'Entitlement decisions must be deterministic and explainable with stable reason codes.',
        'ENT-R020' => 'CLI/API/admin output must not leak secrets or PII.',
        'ENT-R021' => 'The entitlement cache must not create stale privilege escalation.',
        'ENT-R022' => 'Super-admin/platform-admin operations must still be audited when a bypass is explicitly allowed.',
        'ENT-R023' => 'Prior Sprint 24–31 billing/payment/lifecycle semantics must not be bypassed.',
        'ENT-R024' => 'Go/no-go must verify runtime enforcement for all core limits.',
    ],

    // The Sprint 32 entitlement commands (self-contract surfaced in go-no-go).
    'entitlement_commands' => [
        'entitlement:plan-summary',
        'entitlement:usage-summary',
        'entitlement:access-check',
        'entitlement:decision-summary',
        'entitlement:governance-audit',
        'entitlement:go-no-go',
    ],

    // Cumulative prior-sprint gate commands (Sprint 24–31) that must remain
    // registered for the entitlement gate (prior-sprint gate contract, ENT-R023).
    'required_commands' => [
        'subscription-renewal:go-no-go',
        'tenant-lifecycle:go-no-go',
        'tenant-plan:go-no-go',
        'report-export-metering:go-no-go',
        'usage-ledger:go-no-go',
        'export-governance:go-no-go',
        'billing:go-no-go',
        'payment-gateway:go-no-go',
    ],

    // Prior-sprint gate contract surfaced in the entitlement GO/WATCH/NO-GO.
    'prior_sprint_gates' => [
        'subscription_renewal_gate' => ['subscription-renewal:go-no-go'],
        'tenant_lifecycle_gate'     => ['tenant-lifecycle:go-no-go'],
        'tenant_plan_gate'          => ['tenant-plan:go-no-go'],
        'report_export_gate'        => ['report-export-metering:go-no-go'],
        'usage_ledger_gate'         => ['usage-ledger:go-no-go'],
        'export_governance_gate'    => ['export-governance:go-no-go'],
        'billing_gate'              => ['billing:go-no-go'],
        'payment_gateway_gate'      => ['payment-gateway:go-no-go'],
    ],

    // Documentation contract (surfaced in go-no-go). Paths resolve from repo root.
    'required_docs' => [
        'docs/architecture/sprint-32-plan-entitlement-runtime-enforcement-subscription-access-control.md',
        'docs/sprints/sprint-32-plan-entitlement-runtime-enforcement-evidence.md',
    ],

    // Android release readiness script (reused prior-sprint contract).
    'android_release_readiness_script' => 'scripts/android_release_readiness.sh',
];
