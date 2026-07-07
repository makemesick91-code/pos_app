# Daily Pilot Monitoring Runbook

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> Governed by `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` and `docs/PROJECT_RULES.md`.
> No real credentials, no real server IP/token, no real customer data in this runbook.

This runbook drives the daily pilot monitoring routine during hypercare. It is
evidence-based and produces a **GO / WATCH / NO-GO** daily decision. It does not
send real alerts and does not mutate production data.

## Schedule (placeholder)

| Window | Owner (placeholder) | Notes |
|--------|---------------------|-------|
| Morning open (`HH:MM`) | `hypercare-lead@example.test` | Pre-open health sweep |
| Midday (`HH:MM`) | `hypercare-lead@example.test` | Peak-hours sweep |
| Close (`HH:MM`) | `hypercare-lead@example.test` | Closing + report sweep |

## Automated gate

```bash
cd backend && php artisan pilot:daily-monitoring-check --json
cd backend && php artisan pilot:health-summary --json
cd backend && php artisan hypercare:issue-triage --json
```

`pilot:daily-monitoring-check` maps to the canonical monitoring signals below.

## Daily monitoring signals

- [ ] **backend_health** — `GET /api/health` returns healthy.
- [ ] **auth_login** — operator login succeeds; token issued.
- [ ] **tenant_context** — `/api/v1/tenant-context` scoped to the pilot tenant.
- [ ] **product_sync** — `/api/v1/sync/products` + `/sync/categories` return the catalog.
- [ ] **cashier_cash_sale** — a cash sale posts and is accepted (idempotent).
- [ ] **qris_payment_status** — QRIS payment status transitions PENDING → PAID.
- [ ] **receipt_printer** — receipt payload renders; ESC/POS print check.
- [ ] **offline_cash_queue** — offline cash queue length is bounded.
- [ ] **offline_sync_retry** — queued offline sales retry and drain after network returns.
- [ ] **inventory_movement** — sale-out movement recorded; current stock derived.
- [ ] **daily_sales_report** — `/api/v1/reports/daily-sales` returns for the pilot day.
- [ ] **daily_closing** — daily closing snapshot locks correctly.
- [ ] **subscription_device_status** — subscription active; device within limit.
- [ ] **admin_onboarding** — admin/onboarding health unchanged.
- [ ] **demo_data_reset_guard** — demo reset guard blocks non-demo mutation.

## Issue register review

- [ ] Review `docs/pilot/field-issue-register.md` and `hypercare-issue-triage-workflow.md`.
- [ ] Run `hypercare:issue-triage --json`; confirm no open BLOCKER/CRITICAL.
- [ ] Capture operator feedback in `operator-feedback-log.md`.

## Daily GO/WATCH/NO-GO decision

| Decision | Condition |
|----------|-----------|
| GO | All signals PASS, no open BLOCKER/CRITICAL/MAJOR |
| WATCH | Non-critical WARN, or open MAJOR with mitigation |
| NO-GO | Any critical signal FAIL, or open BLOCKER/CRITICAL |

Record the daily result in `docs/pilot/pilot-health-summary-template.md`.
