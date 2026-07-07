# Production Handover Pack

Sprint 18 — Pilot Closure & Production Handover Foundation.

The production handover pack bundles the whole system into a single reviewable,
sign-off-driven artifact (`production_handover_packages`). Run
`php artisan production:handover-summary --json` for the readiness decision.
`candidate_commit` / `candidate_tag` are references only — never credentials.

## System overview

Aish POS Lite is a **multi-tenant Android POS SaaS** (not a single-store POS).
Backend = Laravel API (authoritative for payments, receipts, reports, closing).
Android = lightweight cashier device app (`com.aishtech.poslite`, minSdk 26,
targetSdk 35).

## Backend handover

- Laravel API, Sanctum auth, tenant-context + subscription/device middleware.
- Migrations, models, services, and admin control panel under `backend/`.
- Release readiness: `production:readiness-check`, `release:go-no-go`.

## Android handover

- Debug build gate is CI (`assembleDebug`, `testDebugUnitTest`).
- Release readiness: `scripts/android_release_readiness.sh`.
- Artifacts are handled as checklists/evidence — **no APK/AAB/keystore committed**.
  See `docs/pilot/android-rc-artifact-handling.md`.

## Admin SaaS handover

- Platform-admin-only cross-tenant control panel (`/api/v1/admin/*`,
  `platform.admin`), with `AdminAuditLog` on every mutation.

## Tenant onboarding handover

- Platform-admin onboarding (`/api/v1/admin/tenant-onboarding`) + demo data
  seed/reset (idempotent, guarded). No public signup.

## Subscription / device handover

- Backend-computed subscription status + device-limit enforcement (Sprint 10).

## QRIS / payment handover

- Backend-driven QRIS only; gateway credentials backend-only; webhook verified
  by signature. Android never calls a gateway directly.

## Offline sync handover

- Offline CASH only; QRIS is online-only. Sales are idempotent; WorkManager sync.

## Inventory / report / closing handover

- Ledger-based inventory (stock derived from movements); backend-authoritative
  reports; daily closing snapshot lock.

## Release / runbook references

- `docs/release/production-readiness-checklist.md`
- `docs/release/backup-restore-runbook.md`
- `docs/release/release-go-no-go-runbook.md`
- [backup-restore-handover.md](backup-restore-handover.md)

## Support / SLA references

- [support-sla-handover.md](support-sla-handover.md)
- [release-ownership-matrix.md](release-ownership-matrix.md)

No real passwords, server IPs, gateway secrets, or customer data appear here.
