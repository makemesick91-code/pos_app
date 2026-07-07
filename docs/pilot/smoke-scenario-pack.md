# Smoke Scenario Pack

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

A fast, repeatable set of scenarios to confirm the pilot RC is healthy before a
full operator UAT run. Each scenario is deliberately small and evidence-backed.
Backend endpoints are referenced by their route URI; no secrets appear here.

Format per scenario: **Scenario ID · Purpose · Precondition · Steps · Expected
Result · Evidence Required · Blocking?**

---

### SMK-01 · Backend health
- Purpose: API process is up.
- Precondition: backend deployed to pilot environment.
- Steps: `GET api/health`.
- Expected Result: HTTP 200, healthy payload.
- Evidence Required: response status screenshot/log.
- Blocking? yes

### SMK-02 · Auth login
- Purpose: operator can authenticate.
- Precondition: demo tenant + operator user exist.
- Steps: `POST api/v1/auth/login` with placeholder operator credentials.
- Expected Result: token issued, tenant scoped.
- Evidence Required: 200 + token presence (redacted).
- Blocking? yes

### SMK-03 · Tenant context
- Purpose: tenant isolation intact.
- Precondition: authenticated.
- Steps: `GET api/v1/tenant-context`.
- Expected Result: correct tenant/store returned.
- Evidence Required: tenant id/name (demo).
- Blocking? yes

### SMK-04 · Product sync
- Purpose: catalog reaches device.
- Precondition: authenticated.
- Steps: `GET api/v1/sync/products`, `GET api/v1/sync/categories`.
- Expected Result: catalog + categories returned, tenant-scoped.
- Evidence Required: item count.
- Blocking? yes

### SMK-05 · Current stock
- Purpose: inventory readable.
- Precondition: authenticated.
- Steps: `GET api/v1/inventory/current-stock`.
- Expected Result: stock levels returned.
- Evidence Required: sample stock row.
- Blocking? no

### SMK-06 · Cash sale
- Purpose: core POS transaction.
- Precondition: catalog synced.
- Steps: create sale → `POST api/v1/sales/{sale}/payments/cash`.
- Expected Result: sale completed, totals correct.
- Evidence Required: sale id + total.
- Blocking? yes

### SMK-07 · Receipt
- Purpose: receipt generation.
- Precondition: completed sale.
- Steps: `GET api/v1/sales/{sale}/receipt`.
- Expected Result: receipt payload matches sale.
- Evidence Required: receipt preview.
- Blocking? yes

### SMK-08 · Offline cash sync behavior
- Purpose: offline cash sale queues and syncs.
- Precondition: device offline.
- Steps: create cash sale offline → restore network → sync.
- Expected Result: queued sale uploads once, no duplicate.
- Evidence Required: pre/post sync sale count.
- Blocking? yes

### SMK-09 · QRIS pending/status behavior
- Purpose: online QRIS status flow.
- Precondition: authenticated, online.
- Steps: `POST api/v1/sales/{sale}/payments/qris` → poll `GET api/v1/payments/{payment}/status`.
- Expected Result: pending → resolved status transition (backend-driven).
- Evidence Required: status transition log.
- Blocking? no

### SMK-10 · Daily report
- Purpose: reporting works.
- Precondition: at least one sale.
- Steps: `GET api/v1/reports/daily-sales`.
- Expected Result: totals reflect the day's sales.
- Evidence Required: report totals.
- Blocking? no

### SMK-11 · Closing
- Purpose: daily closing snapshot.
- Precondition: sales recorded.
- Steps: `POST api/v1/closings/daily`.
- Expected Result: closing snapshot locked.
- Evidence Required: closing id.
- Blocking? no

### SMK-12 · Subscription status
- Purpose: subscription gate visible.
- Precondition: authenticated.
- Steps: `GET api/v1/subscription/status`.
- Expected Result: subscription state returned.
- Evidence Required: status value.
- Blocking? yes

### SMK-13 · Device registration
- Purpose: device limit enforcement.
- Precondition: authenticated.
- Steps: `POST api/v1/devices/register`, then heartbeat.
- Expected Result: device registered within limit; over-limit denied.
- Evidence Required: device uuid (demo).
- Blocking? yes

### SMK-14 · Admin tenant onboarding
- Purpose: platform admin can onboard demo tenant.
- Precondition: platform admin token.
- Steps: `POST api/v1/admin/tenant-onboarding`.
- Expected Result: demo tenant provisioned idempotently.
- Evidence Required: onboarding run id.
- Blocking? no

### SMK-15 · Release readiness command
- Purpose: release gate is runnable.
- Precondition: backend environment.
- Steps: `php artisan release:go-no-go --json`, `php artisan pilot:rc-check --json`.
- Expected Result: GO or WATCH, no secrets in output.
- Evidence Required: decision JSON (redacted).
- Blocking? yes
