# Tenant Plan, Feature Entitlement & Usage Limit Governance (Sprint 26)

Server-side foundation that decides **what a tenant may do** (feature entitlement)
and **how much** (usage limits), based on the tenant's **plan** — the single
server-side source of truth. Enforcement is authoritative on the backend; the
Android/POS client is UX only and is never the enforcement authority
(`TPE-R002`, `TPE-R010`).

## Dependency map

```
tenant
  └─ lifecycle status (Sprint 25)  ── GUARD 1: tenant.lifecycle (423 TENANT_SUSPENDED)
        └─ subscription (Sprint 10/24) ── subscription.active (402)
              └─ plan assignment (Sprint 26) ── TenantPlanResolver → TenantPlanDecision
                    ├─ plan entitlements ── FeatureEntitlementService / TenantEntitlementGuard
                    │        └─ GUARD 2: tenant.entitled:<feature> (403 FEATURE_NOT_ENTITLED)
                    └─ plan usage limits ── TenantUsageLimitService / TenantUsageMeter
                             └─ GUARD 3: tenant.usage.limit:<key> (429 USAGE_LIMIT_EXCEEDED)
platform.admin ── plan catalogue / plan assignment / entitlement override / summary
```

Guard order on every protected operational route (lifecycle precedence,
`TPE-R004`):

```
auth:sanctum → tenant.active → tenant.context → subscription.active →
tenant.lifecycle → device.registered → tenant.entitled:<feature> → tenant.usage.limit:<key>
```

A suspended/cancelled/archived tenant is blocked by `tenant.lifecycle` **before**
entitlement/usage is ever consulted, so a plan or override can never re-enable it
(`TPE-R005`).

## Source of truth

- Canonical definition: `backend/config/tenant_plan.php` (plan matrix, entitlement
  registry, usage-limit registry, rules `TPE-R001..R012`, guardrails).
- Persisted source of truth: `tenant_plans`, `plan_entitlements`,
  `plan_usage_limits` — synced from config by `TenantPlanRegistrar` (`TPE-R001`).
- Tenant plan: the tenant's ACTIVE `tenant_plan_assignments` row within its
  effective window; no assignment → safe default (restricted) plan `starter`.

## Central services

| Concern | Service | Decision object |
|---|---|---|
| Plan resolution | `TenantPlanResolver` | `TenantPlanDecision` |
| Entitlement | `FeatureEntitlementService` + `TenantEntitlementGuard` | `EntitlementDecision` |
| Usage limit | `TenantUsageLimitService` + `TenantUsageMeter` | `UsageLimitDecision` |
| Plan assignment | `TenantPlanAssignmentService` | audit-logged |
| Entitlement override | `TenantEntitlementOverrideService` | audit-logged |
| Governance summary | `TenantPlanSummaryService` | — |
| Enforcement audit | `TenantPlanEnforcementAuditService` | GO/WATCH/NO_GO |
| Readiness | `TenantPlanReadinessService` | GO/WATCH/NO_GO |
| GO/WATCH/NO-GO | `TenantPlanGoNoGoService` | GO/WATCH/NO_GO |

## Response contract

- Entitlement denied → HTTP **403**, `{ "code": "FEATURE_NOT_ENTITLED", "feature": "…" }`.
- Usage limit exceeded → HTTP **429**, `{ "code": "USAGE_LIMIT_EXCEEDED", "limit": "…" }`.
- Suspended tenant → HTTP **423**, `{ "code": "TENANT_SUSPENDED" }` (Sprint 25, unchanged).

## Governance & safety

- Plan assignment and entitlement override are **platform.admin only**, audit-logged
  with redacted metadata (`TPE-R006`, `TPE-R007`). Override reason is mandatory and
  sanitized (`TPE-R008`).
- Nothing charges, calls a payment gateway, or mutates subscription
  renewal/dunning (Sprint 24) or manual suspension (Sprint 25).
- Commands: `tenant-plan:readiness`, `tenant-plan:entitlement-summary`,
  `tenant-plan:usage-limit-summary`, `tenant-plan:enforcement-audit`,
  `tenant-plan:go-no-go`.
