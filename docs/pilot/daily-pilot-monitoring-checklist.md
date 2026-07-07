# Daily Pilot Monitoring Checklist

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Run once per pilot day. Log anomalies in `field-issue-register.md`. Placeholders
only.

Date: `YYYY-MM-DD` · Tenant: `DEMO_TENANT_PLACEHOLDER` · Reviewer: `REVIEWER_PLACEHOLDER`.

| # | Daily check | Source | Result |
|---|-------------|--------|--------|
| 1 | Operator login | auth log / device | ☐ |
| 2 | Sales count | `/api/v1/reports/daily-sales` | ☐ |
| 3 | Payment status | QRIS/cash payment statuses reconciled | ☐ |
| 4 | Offline sync queue | pending queue drained | ☐ |
| 5 | Failed sync | zero unresolved sync failures | ☐ |
| 6 | Receipt/printer issues | operator report | ☐ |
| 7 | Inventory movement anomaly | movement summary sane | ☐ |
| 8 | Closing completed | daily closing snapshot present | ☐ |
| 9 | Device/subscription status | active + within limit | ☐ |
| 10 | Open issue review | `field-issue-register.md` triaged | ☐ |

## Escalation

- Any BLOCKER/CRITICAL anomaly => trigger `pilot-rollback-checklist.md`.
- Roll findings up into `field-trial-go-watch-no-go-report.md`.
