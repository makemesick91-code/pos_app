# Sprint 26 — Tenant Plan, Feature Entitlement & Usage Limit Governance Foundation

## Scope

Server-side foundation for **tenant plans**, **feature entitlement**, and **usage
limits**, with real runtime enforcement layered after Sprint 25 tenant lifecycle
enforcement. Plans are the single server-side source of truth; the Android/POS
client is UX only (`TPE-R002`, `TPE-R010`).

## Runtime enforcement

- New middleware:
  - `tenant.entitled:<feature>` → `EnsureTenantEntitled` — 403 `FEATURE_NOT_ENTITLED`.
  - `tenant.usage.limit:<key>` → `EnsureTenantUsageLimitAvailable` — 429 `USAGE_LIMIT_EXCEEDED`.
- Applied to operational route groups after `tenant.lifecycle` (lifecycle
  precedence, `TPE-R004`):
  - `inventory.basic` → products / product-categories / product-store-prices / sync / inventory.
  - `pos.sales` → sales / receipt / QRIS+cash payments.
  - `reports.basic` → reports / daily closings.
- Usage-metered mutations: `POST /products` → `products.max`, `POST /sales` →
  `transactions.monthly`.

## Migrations / schema

- `tenant_plans` — plan catalogue (key/name/description/status/billing_interval/metadata).
- `plan_entitlements` — per-plan feature flags.
- `plan_usage_limits` — per-plan numeric/unlimited limits with period.
- `tenant_plan_assignments` — tenant → plan (status/effective window/source/reason/audit).
- `tenant_entitlement_overrides` — per-tenant feature override (enabled/reason/window).

## Plan source of truth

`config/tenant_plan.php` defines the canonical matrix; `TenantPlanRegistrar` syncs
it into the catalogue tables. `TenantPlanResolver` resolves a tenant's plan from
its ACTIVE assignment or the safe default (`starter`). See
[plan-source-of-truth](../tenant-plan/plan-source-of-truth.md).

## Entitlement & usage governance

- `FeatureEntitlementService` / `TenantEntitlementGuard` / `EntitlementDecision`.
- `TenantUsageLimitService` / `TenantUsageMeter` / `UsageLimitDecision` — current
  usage from real DB counts; `reports.exports.monthly` is a documented deferred
  meter. See [feature-entitlement-governance](../tenant-plan/feature-entitlement-governance.md)
  and [usage-limit-governance](../tenant-plan/usage-limit-governance.md).

## Tenant lifecycle precedence

`tenant.lifecycle` runs first; a suspended tenant with a valid plan is still
`TENANT_SUSPENDED`; plan/override can never re-enable it (`TPE-R005`). Sprint 24
renewal/dunning and Sprint 25 lifecycle/suspension are unchanged. See
[lifecycle-precedence](../tenant-plan/lifecycle-precedence.md).

## Platform admin endpoints (platform.admin only, audit-logged)

- `GET/POST /api/v1/admin/tenant-plans`, `PATCH /api/v1/admin/tenant-plans/{plan}`
- `GET/POST /api/v1/admin/tenants/{tenant}/plan`
- `GET /api/v1/admin/tenants/{tenant}/entitlements`, `POST …/entitlement-overrides`
- `GET /api/v1/admin/tenants/{tenant}/usage-limits`
- `GET /api/v1/admin/tenant-plan-governance/summary`

## Rules added

`TPE-R001..R012` in `config/tenant_plan.php` and `docs/PROJECT_RULES.md`; foundation
flags in `config/pos_foundation.php` (`sprint_26`). CI greps `TPE-R004` and
`TPE-R012`.

## Commands

- `tenant-plan:readiness`
- `tenant-plan:entitlement-summary`
- `tenant-plan:usage-limit-summary`
- `tenant-plan:enforcement-audit`
- `tenant-plan:go-no-go`

Prior gates (`tenant-lifecycle:go-no-go`, Sprint 24 renewal, Sprint 13–25) remain
green.

## Tests

`TenantPlanResolutionTest`, `TenantPlanEntitlementEnforcementTest`,
`TenantPlanUsageLimitEnforcementTest`, `TenantPlanLifecyclePrecedenceTest`,
`TenantPlanAdminApiTest`, `TenantPlanCommandsTest`, `TenantPlanRulesLockTest`, plus
Android `TenantPlanAccessMessageTest`.

## Known deferred meters

- `reports.exports.monthly` — declared limit, live metering deferred (report-export
  events not yet persisted); reported as `meterable: false`, never blocks.

## Evidence

- Backend tests / smoke / CI: see PR.
- GO tag: `sprint-26-tenant-plan-feature-entitlement-usage-limit-governance-foundation-go`.
