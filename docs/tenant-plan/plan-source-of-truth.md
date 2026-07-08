# Plan Source of Truth (Sprint 26)

`TPE-R001` — a tenant's plan has a single server-side source of truth.

## Layers

1. **Canonical definition** — `backend/config/tenant_plan.php`. Defines the plan
   keys (`starter`, `growth`, `professional`, `enterprise`), the entitlement
   registry, the usage-limit registry, and the per-plan matrix.
2. **Persisted catalogue** — `tenant_plans`, `plan_entitlements`,
   `plan_usage_limits`. `TenantPlanRegistrar::sync()` upserts the config
   definition into these tables (idempotent). `ensure()` lazily syncs once per
   process if the catalogue is empty, so resolution always has a populated source
   of truth. The registrar is the only writer of the catalogue tables — nothing is
   created from client input.
3. **Tenant assignment** — `tenant_plan_assignments`. An ACTIVE row within its
   effective window (`effective_from`/`effective_until`) is the authoritative plan
   for a tenant. `TenantPlanAssignmentService` supersedes the previous active
   assignment on change and audit-logs it.

## Resolution

`TenantPlanResolver::resolve(tenant)` returns an immutable `TenantPlanDecision`:

1. If the tenant has an ACTIVE assignment → that plan.
2. Otherwise → the **safe default plan** (`config('tenant_plan.default_plan')` =
   `starter`), a real, restricted plan. A tenant with no assignment is therefore
   never "unlimited"; it gets a restricted-but-functional decision.

Plans are never computed ad-hoc in a controller. The decision carries the plan
key/name, whether the assignment is explicit, the entitlement flags, and the
usage limits.

## Default plans

| Concern | Plan | Rationale |
|---|---|---|
| Production no-assignment | `starter` | Safe restricted default, never a bypass. |
| Test factory tenants | `enterprise` | Unlimited/all-entitled so the cumulative Sprint 2–25 suites stay green under the new guards (mirrors the Sprint 10 auto-provision convention). |

Backend-only; never mutated by client input; never charges.
