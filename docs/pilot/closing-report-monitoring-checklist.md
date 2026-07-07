# Closing / Report Completion Monitoring Checklist

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> Reports and closings are backend-authoritative and tenant-isolated.

Monitors daily reporting and closing completion during the pilot. Maps to the
`daily_sales_report`, `inventory_movement`, and `daily_closing` signals.

## Checks

- [ ] **Daily sales report** — `/api/v1/reports/daily-sales` returns for the
      pilot day with expected totals.
- [ ] **Payment summary** — cash vs. QRIS breakdown reconciles with sales.
- [ ] **Inventory movement summary** — sale-out movements recorded; current
      stock derived from movements.
- [ ] **Daily closing** — `/api/v1/closings/daily` produces a locked snapshot.
- [ ] **Duplicate closing replay** — replaying the daily closing does not create
      a second snapshot (idempotent) and does not mutate the locked one.
- [ ] **CSV/export readiness** — export (if used) is tenant-isolated; no
      cross-tenant data leakage.

## Evidence required

- Daily sales report + payment summary (anonymized totals).
- Closing snapshot reference; duplicate-replay result.

## Escalation

- Closing snapshot not locking / mutable after close → CRITICAL.
- Duplicate closing snapshot created on replay → BLOCKER.
- Cross-tenant data in any report/export → BLOCKER.
