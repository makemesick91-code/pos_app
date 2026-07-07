# Release Ownership Matrix

Sprint 18 — Pilot Closure & Production Handover Foundation.

Ownership for each production area. Roles are placeholders (OWNER / TECHNICAL /
SUPPORT / ADMIN / OPERATOR) — no real names or credentials required.

| Area | Owner Role | Backup Role | Responsibility | Evidence/Runbook |
|------|------------|-------------|----------------|------------------|
| Backend | TECHNICAL | ADMIN | Laravel API, migrations, services, admin panel | `docs/release/production-readiness-checklist.md` |
| Android | TECHNICAL | OPERATOR | POS device app build/test/release readiness | `scripts/android_release_readiness.sh` |
| Database | TECHNICAL | ADMIN | Schema, migrations, integrity | `docs/release/backup-restore-runbook.md` |
| Backup/Restore | TECHNICAL | ADMIN | Backups, restore rehearsal, retention | [backup-restore-handover.md](backup-restore-handover.md) |
| QRIS/Payment | TECHNICAL | OWNER | Backend-driven QRIS, gateway config, webhooks | `docs/pilot/payment-qris-monitoring-checklist.md` |
| Subscription/Device | ADMIN | TECHNICAL | Subscription status, device-limit enforcement | `docs/pilot/device-subscription-anomaly-checklist.md` |
| Tenant Onboarding | ADMIN | OWNER | Onboarding, demo data, tenant setup | `docs/pilot/demo-tenant-pilot-setup-evidence.md` |
| Inventory/Reports/Closing | TECHNICAL | ADMIN | Ledger inventory, reports, daily closing | `docs/pilot/closing-report-monitoring-checklist.md` |
| Support/SLA | SUPPORT | ADMIN | Issue intake, SLA, escalation | [support-sla-handover.md](support-sla-handover.md) |
| Release/Tagging | OWNER | TECHNICAL | GO/WATCH/NO-GO decision, tags | [production-go-watch-no-go-report.md](production-go-watch-no-go-report.md) |

Each area has an owner and a backup so no single point of failure exists at
handover. No secrets appear in this matrix.
