# Production Operations Runbook

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

This runbook governs day-to-day production operations of the Aish POS Lite
multi-tenant Android POS SaaS **after** the Sprint 18 production handover. It is a
governance/evidence document. It never triggers a real deployment, never runs a
real backup or restore, and never sends real alerts. No real credentials, server
IPs, tokens, or customer data appear here — those live only in the operator's
secret store.

## Ownership & escalation (placeholder)

| Role | Responsibility |
| --- | --- |
| Operations owner | Daily/weekly checks, incident intake, GO/WATCH/NO-GO recommendation |
| Technical owner | Backend/Android/database health, release/rollback execution |
| Support owner | SLA compliance, operator communications |

Escalation path (placeholder — fill with real contacts in the operator secret store):
`Operator → Support owner → Technical owner → Operations owner`.

## Daily operations checklist

- [ ] `php artisan production:ops-health --json` reviewed; decision recorded.
- [ ] `php artisan production:incident-summary --json` reviewed; no open P0/P1 without accepted risk.
- [ ] Open incident SLA state reviewed (no blocking incident past SLA).
- [ ] Backend health checks reviewed (API reachable, auth login, tenant context).
- [ ] Android app health checks reviewed (latest release readiness evidence).
- [ ] Payment/QRIS health checks reviewed (webhook processing, payment status).
- [ ] Offline sync health checks reviewed (queue drain, idempotent replay).

## Weekly operations checklist

- [ ] `php artisan production:backup-governance-check --json` reviewed.
- [ ] `php artisan production:post-handover-go-no-go --json` reviewed.
- [ ] Backup verification evidence confirmed for the week.
- [ ] Restore rehearsal cadence on track.
- [ ] Maintenance windows for the coming week reviewed (rollback plan present for HIGH/CRITICAL).
- [ ] Subscription/device health reviewed (limits enforced, no anomalies).
- [ ] Reports/closing health reviewed (daily closing snapshots consistent).

## Backend health checks

- API reachability, auth login (Sanctum token issuance), tenant context resolution.
- Database connection and migration status (`production:readiness-check`).

## Android app health checks

- Latest Android release readiness evidence (`scripts/android_release_readiness.sh` in CI).
- Package `com.aishtech.poslite`, minSdk 26, targetSdk 35 unchanged.

## Database health checks

- Connection reachable; migrations applied; backup verification evidence current.

## Payment / QRIS health checks

- QRIS payment status polling and webhook processing healthy (no stuck payments).
- Payment gateway credentials remain backend-only (never on device, never in docs).

## Offline sync health checks

- Sync queue drains; sale idempotency keys prevent duplicates on replay.

## Subscription / device health checks

- Subscription active; device limit enforced; no unexpected device registrations.

## Reports / closing health checks

- Daily closing snapshots lock correctly; CSV export remains tenant-isolated.

## Safety rules

- No automatic production deployment from this runbook.
- No real backup/restore execution from operations commands.
- No real Slack/WhatsApp/email alert sending.
- No secrets, keystores, APK/AAB, or customer data committed to the repository.
