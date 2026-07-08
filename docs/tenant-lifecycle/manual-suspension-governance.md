# Manual Suspension Governance (Sprint 25)

How a platform admin manually suspends and lifts a tenant, and the guarantees
around it.

## Authorization (TLS-R002)

All manual suspension mutations sit behind `auth:sanctum` + `platform.admin`.
Tenant business users (owners, cashiers) are always blocked with
`PLATFORM_ADMIN_REQUIRED`. No client input can grant this.

## Endpoints (platform admin only)

| Method | Path                                             | Action                     |
|--------|--------------------------------------------------|----------------------------|
| GET    | `/api/v1/admin/tenants/{tenant}/lifecycle`       | View lifecycle + events    |
| POST   | `/api/v1/admin/tenants/{tenant}/suspend`         | Manually suspend           |
| POST   | `/api/v1/admin/tenants/{tenant}/lift-suspension` | Lift manual suspension     |
| GET    | `/api/v1/admin/tenant-lifecycle/suspension-summary` | Governance summary      |

## Data model

- `tenant_manual_suspensions` — `status` (`ACTIVE` / `LIFTED`), `reason`,
  `reason_category`, `effective_at`, `lifted_at`, `lift_reason`,
  `suspended_by_user_id`, `lifted_by_user_id`, `metadata`. An `ACTIVE` row is the
  authoritative manual-suspension signal.
- `tenant_lifecycle_events` — append-only trail of `manual_suspend`,
  `manual_lift`, `lifecycle_transition` with previous/new status.

## Guarantees

- **Idempotent.** Suspending an already-suspended tenant returns the existing
  suspension (`already_suspended: true`, HTTP 200) without creating a duplicate.
  Lifting a tenant that is not suspended returns `not_suspended: true`, HTTP 200.
- **Reason mandatory + sanitized (TLS-R006).** Reason is required (min 3 chars).
  `SanitizesTenantLifecycleText` masks `secret:`, `token=`, `password:`,
  `api_key`, etc. so credentials can never be persisted. Metadata drops
  secret-looking keys and non-scalar leaves.
- **Reason category** is validated against the allowlist in
  `config/tenant_lifecycle.php` (`PAYMENT_OVERDUE`, `ABUSE`, `FRAUD_REVIEW`,
  `SECURITY`, `CONTRACT_TERMINATION`, `CUSTOMER_REQUEST`, `COMPLIANCE`, `OTHER`).
- **Audit-logged (TLS-R005).** Each suspend/lift writes an `admin_audit_logs`
  row (`tenant.manual_suspend` / `tenant.lift_suspension`) via `AdminAuditLogger`
  with before/after lifecycle status and redacted metadata, plus a
  `tenant_lifecycle_events` record.
- **No auto-reactivation (TLS-R004).** Only an explicit platform-admin lift can
  clear a manual suspension. Renewal/dunning automation never writes these tables.
- **No hard delete, no public API, no real messaging** — all disabled by
  guardrail flags that force NO_GO if enabled.
