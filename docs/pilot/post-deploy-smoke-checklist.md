# Post-Deploy Smoke Checklist

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Executed immediately after a pilot deploy against the demo tenant. Each scenario
follows the same structure. Placeholders only — no real credentials or customer
data.

Scenario structure: **Scenario ID · Purpose · Precondition · Steps · Expected
Result · Evidence Required · Blocking?**

---

### SMK-01 · Backend health
- Purpose: confirm backend reachable after deploy.
- Precondition: deploy dry-run complete.
- Steps: `GET /api/health`.
- Expected: `200 OK`.
- Evidence: response status.
- Blocking: yes

### SMK-02 · Auth login
- Purpose: operator can authenticate.
- Precondition: demo tenant onboarded.
- Steps: `POST /api/v1/auth/login` with `operator@example.test`.
- Expected: token issued.
- Evidence: token present (redacted).
- Blocking: yes

### SMK-03 · Tenant context
- Purpose: correct tenant scoping.
- Precondition: logged in.
- Steps: `GET /api/v1/tenant-context`.
- Expected: `DEMO_TENANT_PLACEHOLDER` context.
- Evidence: tenant id.
- Blocking: yes

### SMK-04 · Product sync
- Purpose: catalog reaches device.
- Precondition: demo products seeded.
- Steps: `GET /api/v1/sync/products`.
- Expected: products returned.
- Evidence: count.
- Blocking: yes

### SMK-05 · Stock visibility
- Purpose: current stock visible.
- Steps: `GET /api/v1/inventory/current-stock`.
- Expected: stock from movements.
- Evidence: sample row.
- Blocking: no

### SMK-06 · Cash sale
- Purpose: cash checkout works.
- Steps: create sale + `payments/cash`.
- Expected: sale paid, invoice number issued.
- Evidence: invoice number.
- Blocking: yes

### SMK-07 · QRIS status
- Purpose: online QRIS status reachable.
- Steps: create QRIS payment, poll `payments/{payment}/status`.
- Expected: status transitions (sandbox/fake gateway).
- Evidence: status value.
- Blocking: yes

### SMK-08 · Receipt
- Purpose: backend-authoritative receipt.
- Steps: `GET /api/v1/sales/{sale}/receipt`.
- Expected: receipt payload.
- Evidence: receipt id.
- Blocking: yes

### SMK-09 · Printer
- Purpose: ESC/POS print on paired printer.
- Steps: print receipt from device.
- Expected: printed slip.
- Evidence: photo/log.
- Blocking: no

### SMK-10 · Offline cash
- Purpose: cash sale offline.
- Steps: airplane mode, create cash sale.
- Expected: sale queued locally.
- Evidence: queue entry.
- Blocking: yes

### SMK-11 · Offline sync
- Purpose: queued sale syncs.
- Steps: restore network, allow WorkManager sync.
- Expected: sale synced idempotently.
- Evidence: server sale id.
- Blocking: yes

### SMK-12 · Daily report
- Purpose: report reflects sales.
- Steps: `GET /api/v1/reports/daily-sales`.
- Expected: totals match.
- Evidence: totals.
- Blocking: no

### SMK-13 · Closing
- Purpose: daily closing snapshot.
- Steps: `POST /api/v1/closings/daily`.
- Expected: closing locked snapshot.
- Evidence: closing id.
- Blocking: yes

### SMK-14 · Subscription/device status
- Purpose: gate enforced.
- Steps: `GET /api/v1/subscription/status`, `GET /api/v1/devices`.
- Expected: active + within limit.
- Evidence: status.
- Blocking: yes

### SMK-15 · Admin onboarding status
- Purpose: onboarding state visible.
- Steps: `GET /api/v1/admin/tenants/{tenant}/onboarding-status`.
- Expected: onboarding complete.
- Evidence: status.
- Blocking: no

---

Any BLOCKING scenario failure => log a BLOCKER/CRITICAL entry in
`field-issue-register.md` and trigger `pilot-rollback-checklist.md`.
