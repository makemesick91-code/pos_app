# 50 — Data Privacy, Audit & Redaction

How Aish POS records admin activity and protects sensitive data.

## Audit every privileged action
- Platform-admin mutations (and security-relevant reads) are logged via
  `App\Services\Admin\AdminAuditLogger::log()` into the `admin_audit_logs` table.
- Audit records capture actor, action, target, and outcome so admin activity is
  reconstructable. Do not add an admin action path that bypasses the logger.

## Redaction is mandatory
- All logged/audited payloads pass through `AdminAuditLogger::sanitize()`, which redacts
  sensitive keys (password, secret, token, and similar). Never log a raw payload directly.
- Payment data, credentials, and tokens are redacted in audit, application logs, API
  responses, and Blade output alike.

## Minimize exposure
- Return only the fields a surface needs. Admin resources and Blade views must not leak
  tenant secrets, hashed token material, or unrelated tenants' data.
- Device activation tokens are stored only as sha256 hashes; raw values are never persisted
  or echoed back.

## Read-only by default on admin surfaces
- Support/observability/admin consoles read canonical ledgers WITHOUT mutating them. A
  diagnostic or view action must never mark an invoice paid, unlock entitlement,
  reactivate a tenant, or bypass suspension.

## Data integrity
- Ledgers are append-only where designed as such; corrections are governed, signed-delta
  repairs through the owning service, not in-place edits. Preserve the auditable trail.
