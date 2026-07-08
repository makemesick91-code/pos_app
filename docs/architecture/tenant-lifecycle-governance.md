# Tenant Lifecycle Governance (Sprint 25)

Server-side authoritative architecture for tenant lifecycle status and manual
suspension governance.

## Source of truth (TLS-R001)

`App\Services\TenantLifecycle\TenantLifecycleService` is the single place that
computes a tenant's lifecycle status and operational-access decision. Controllers
must never recompute lifecycle status ad-hoc. The status vocabulary is defined by
`TenantLifecycleStatus`:

`onboarding`, `active`, `grace`, `past_due`, `suspended`, `cancelled`, `archived`.

`suspended`, `cancelled`, and `archived` are the **blocked** set — the runtime
guard denies operational (POS) access for these.

## Decision precedence (TLS-R004)

`TenantLifecycleService::resolve()` decides in this order:

1. **Active manual suspension wins.** If `tenant.activeManualSuspension()` returns
   an `ACTIVE` `tenant_manual_suspensions` row → `suspended` / blocked, source
   `manual_suspension`, code `TENANT_SUSPENDED`. Subscription renewal and dunning
   automation **cannot** override this; only an explicit platform-admin lift
   clears it.
2. **Legacy tenant status.** `tenant.status = suspended` → `suspended`;
   `tenant.status = inactive` → `archived`. Both blocked.
3. **Subscription refinement (awareness only).** For active tenants the
   subscription decision refines `active → grace / past_due`. Monetary blocking
   stays with the Sprint 10 `subscription.active` middleware (402); the lifecycle
   guard only denies the blocked lifecycle set (423).

The result is an immutable `TenantLifecycleDecision` (status, allowed, code,
reason, source, manuallySuspended, manualSuspensionId).

## Runtime enforcement (TLS-R003, authoritative)

`EnsureTenantLifecycleAllowed` (alias `tenant.lifecycle`) wraps the operational
route group in `routes/api.php` after `subscription.active`. A blocked decision
returns:

```json
{ "message": "Tenant access is suspended.", "code": "TENANT_SUSPENDED", "tenant_status": "suspended" }
```

with HTTP `423 Locked`. Platform admins carry no tenant context and pass through
(TLS-R008). The guard reuses `TenantLifecycleAccessGuard`, the same decision point
used by `tenant-lifecycle:enforcement-audit`, so enforcement is never recomputed
ad-hoc.

The **Android/POS client is UX only and is never the enforcement authority**
(TLS-R009). It surfaces a friendly "tenant suspended — contact your provider"
message; the block is always enforced server-side.

## Manual suspension governance (TLS-R002, TLS-R005, TLS-R006)

`TenantSuspensionService` is the only writer of `tenant_manual_suspensions`:

- `suspend()` / `lift()` are **platform.admin-only** (route middleware) and
  **idempotent** — re-suspending or lifting an already-in-state tenant is a safe
  no-op.
- Reason is **mandatory** and sanitized (`SanitizesTenantLifecycleText`) so no
  secret/token/payment credential is persisted.
- Every mutation appends a `tenant_lifecycle_events` record and an
  `admin_audit_logs` record (via `AdminAuditLogger`, with redacted metadata).

## Enforcement allowlist (TLS-R007, TLS-R008)

See [tenant-lifecycle/enforcement-allowlist.md](../tenant-lifecycle/enforcement-allowlist.md).
Health, auth (login/logout/me), tenant-context, subscription status, device
register/heartbeat/list, and the billing webhook stay reachable while suspended.

## Gates

- `tenant-lifecycle:readiness` — guardrails, status source, suspension store,
  docs, rules, enforcement audit.
- `tenant-lifecycle:suspension-summary` — secret-safe counts.
- `tenant-lifecycle:enforcement-audit` — alias registered + every operational
  route guarded + config contract + guardrails.
- `tenant-lifecycle:go-no-go` — cumulative Sprint 13–24 gates + Sprint 25
  commands + docs + Android readiness + readiness decision (TLS-R010).
