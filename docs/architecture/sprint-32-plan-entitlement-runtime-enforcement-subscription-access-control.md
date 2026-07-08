# Sprint 32 — Plan Entitlement Runtime Enforcement & Subscription Access Control

Canonical foundation: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`. Rules: `docs/PROJECT_RULES.md` (`ENT-R001..R024`), `backend/config/entitlement_governance.php`, `backend/config/pos_foundation.php`.

## Scope

Make SaaS package limits and subscription/billing access **actually enforced at runtime** — not merely stored in config/governance. Sprint 26 already defines *what* a plan grants (feature flags + usage limits) and guards a handful of routes; Sprint 32 adds the single runtime decision layer that combines the plan grant with the tenant's **live billing / subscription / lifecycle state** before a resource is created or a premium/export/report action runs, and records every denial.

## Non-goals

- No new Android entitlement UI (Android stays UX-only; server is authoritative).
- No mutation of the Sprint 23 `saas_billing_*` behaviour.
- No new plan catalogue (reuses Sprint 26 `config/tenant_plan.php`).
- No VPS deploy; no real payment gateway call.
- No time-bound platform override table (not needed; deferred — a governed Sprint 26 entitlement override already exists for features).

## Commercial SaaS chain

```
Plan (S26) → Invoice (S30) → Payment Intent (S31) → Gateway Settlement (S31)
           → Collection (S30) → Entitlement Runtime Access (S32)
```

Sprint 32 is the final link: it reads the settled/collected state (never re-derives it from raw provider events) and turns it into an allow/deny/degrade/read-only decision.

## Entitlement architecture

```
Request / service call
  └─ EntitlementAccessService            (single runtime gate, ENT-R003)
       ├─ TenantPlanResolver             (S26) plan, fail-closed on unknown (ENT-R001/R002)
       ├─ EntitlementBillingStateService (S32) billing/subscription/lifecycle write access
       │     ├─ Tenant::activeManualSuspension()  (S25) — wins (ENT-R013/R014)
       │     ├─ SubscriptionStatusService         (S10) — trial/active/grace/expired/cancelled
       │     └─ TenantBillingInvoice outstanding  (S30) — within-grace vs past-grace
       ├─ FeatureEntitlementService      (S26) plan feature grant (ENT-R009/R010)
       ├─ TenantUsageLimitService        (S26) plan usage cap (OVER_QUOTA, ENT-R004..R008)
       └─ EntitlementAuditService        (S32) persist denied/degraded (ENT-R018) via EntitlementRedactor
```

Decisions are immutable `EntitlementDecision` value objects carrying `allowed`, `status` (allowed/denied/degraded/read_only/bypassed), a stable `reason_code`, a UI-safe `message`, and explaining context (plan, usage, limit, billing/subscription state). No secrets/PII (ENT-R019/R020).

## Runtime enforcement matrix

| Surface | Guard | Denied code | HTTP |
|---|---|---|---|
| Any mutating operational request | `entitlement.write` middleware (verb-aware) | MANUALLY_SUSPENDED / UNPAID_PAST_GRACE / TRIAL_EXPIRED | 423 / 402 |
| Premium feature route | `entitlement.feature:<key>` | FEATURE_NOT_IN_PLAN | 403 |
| Export route | `entitlement.export:<key>` (after S27 meter) | EXPORT_NOT_IN_PLAN / USAGE_LIMIT_EXCEEDED / billing | 403 / 429 / 402 |
| Report route | `entitlement.report:<key>` | REPORT_NOT_IN_PLAN / billing | 403 / 402 |
| Device registration | `tenant.usage.limit:devices.max` (S26) | USAGE_LIMIT_EXCEEDED | 429 |
| Product / sale creation | `tenant.usage.limit:*` (S26) | USAGE_LIMIT_EXCEEDED | 429 |
| Branch/user/cashier/outlet/register creation | `EntitlementAccessService::canCreate*` (service) | OVER_QUOTA | — |

Reads always pass the write gate (existing data stays readable — ENT-R017).

## Limit matrix (Sprint 32 alias → Sprint 26 usage key → resource)

| Alias | Limit key | Resource / action |
|---|---|---|
| branch | branches.max | Store / create |
| outlet | branches.max | Store / create |
| register | branches.max | Store / create |
| user | users.max | User / create |
| cashier | users.max | User (cashier role) / create |
| device | devices.max | active RegisteredDevice / register |

## Feature / export / report entitlement matrix

| Key | Requires entitlement | Meter |
|---|---|---|
| inventory.advanced / reports.advanced / branches.manage (feature) | same key | — |
| reports.daily-sales.csv (export) | reports.basic | reports.exports.monthly |
| reports.daily-sales / reports.payment-summary (report) | reports.basic | — |
| reports.advanced (report) | reports.advanced | — |

## Trial vs paid behaviour

| Billing state | Reason code | Write | Read |
|---|---|---|---|
| active_paid | ALLOWED_ACTIVE_PAID | allow | allow |
| active_trial | ALLOWED_ACTIVE_TRIAL | allow | allow |
| trial_expired | TRIAL_EXPIRED | **deny (read-only)** | allow |
| unpaid_within_grace | ALLOWED_WITHIN_GRACE | allow (**degraded, audited**) | allow |
| unpaid_past_grace | UNPAID_PAST_GRACE | **deny (read-only)** | allow |
| manually_suspended | MANUALLY_SUSPENDED | **deny** | allow* |
| cancelled | SUBSCRIPTION_CANCELLED | **deny** | allow |

\* reads allowed unless `access.block_reads_when_suspended` is enabled.

## Audit / redaction model

Denied / degraded / read_only / bypassed decisions are persisted to `tenant_entitlement_decisions` (allowed reads are not — ENT-R018). `metadata_json` is redacted by `EntitlementRedactor` before write (drops secret/token/credential/card/KTP/NIK/phone/email/name keys and nested payloads; truncates long strings). The table has no tenant-facing writer.

## Route matrix (admin, `platform.admin`, read-only)

```
GET  /admin/tenant-billing/entitlements/plan-summary
GET  /admin/tenant-billing/entitlements/governance-summary
GET  /admin/tenants/{tenant}/tenant-billing/entitlements/summary
GET  /admin/tenants/{tenant}/tenant-billing/entitlements/usage-summary
GET  /admin/tenants/{tenant}/tenant-billing/entitlements/billing-state
GET  /admin/tenant-billing/entitlements/decisions
GET  /admin/tenant-billing/entitlements/decisions/{decision}
GET  /admin/tenant-billing/entitlements/decision-summary
```

No admin/tenant mutation route (`tenant_route_can_mutate_entitlement_state_allowed=false`).

## Middleware / policy matrix

| Alias | Class | Purpose |
|---|---|---|
| entitlement.write | EnsureTenantCanWrite | billing/subscription/lifecycle write gate (verb-aware) |
| entitlement.feature | EnsureFeatureEntitled | premium feature entitlement + audit |
| entitlement.export | EnsureExportEntitled | export entitlement + billing + audit |
| entitlement.report | EnsureReportEntitled | report entitlement + billing + audit |

All run AFTER `tenant.lifecycle`, so manual suspension is still `TENANT_SUSPENDED` first.

## Command matrix

| Command | Purpose |
|---|---|
| entitlement:plan-summary | configured plan limits/features/posture (config only) |
| entitlement:usage-summary | tenant usage vs limits + billing state |
| entitlement:access-check | dry-run (default) a decision; `--record` audits only |
| entitlement:decision-summary | denied/degraded decision counts |
| entitlement:governance-audit | config/rules/guardrails/wiring audit (non-zero on violation) |
| entitlement:go-no-go | hard Sprint 32 gate (GO/WATCH/NO-GO) |

## Data model

`tenant_entitlement_decisions`: id, tenant_id, actor_user_id?, subject_type?, subject_id?, entitlement_key?, resource_type?, action?, decision, reason_code, plan_code?, current_usage?, limit_value?, billing_state?, subscription_state?, metadata_json (redacted), created_at. Indexes: (tenant_id, entitlement_key), (decision, reason_code), created_at, actor_user_id.

## Dependency graph

Sprint 32 depends on: Sprint 10 (subscription), Sprint 25 (manual suspension), Sprint 26 (plan resolver / feature / usage), Sprint 27 (export metering), Sprint 30 (invoice/collection), Sprint 31 (gateway settlement via collection). It weakens none of them (ENT-R023).

## Rollback

Sprint 32 is additive. To roll back: remove the four `entitlement.*` middleware from `routes/api.php` and `bootstrap/app.php`, drop the `tenant_entitlement_decisions` table (`php artisan migrate:rollback`), and remove `config/entitlement_governance.php` and the Sprint 32 services/commands/controllers. Sprint 26 route-level guards and all prior gates continue to function unchanged.

## Tests / CI / smoke evidence

- `backend/tests/Feature/Sprint32EntitlementRuntimeEnforcementTest.php` — 25 tests (governance, billing state, limits, features, export, audit/redaction, middleware, admin surface).
- Full backend suite: **1137 passed, 30558 assertions**.
- `scripts/sprint32_smoke.sh` — 82 checks incl. a deterministic in-DB enforcement probe (below/at limit, suspended, over-quota, unpaid past grace) — **PASS=82 FAIL=0**.
- `.github/workflows/sprint32-ci.yml` — foundation+smoke, backend gate+regression, Android build+unit.

## Deferred risks

- Branch/user/cashier/outlet/register have no tenant-facing CRUD route today; their limits are enforced at the service layer (`canCreate*`) + tests/smoke/command until such routes exist. When added, wrap them with `entitlement.write` and the relevant `tenant.usage.limit` guard.
- A dedicated per-alias usage-limit key (separate cashier/outlet caps) can be added to `config/tenant_plan.php` later without changing the Sprint 32 gate.
