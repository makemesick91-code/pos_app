# Pilot Deployment Checklist

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

This checklist governs a **non-destructive, secret-safe** pilot deployment of
Aish POS Lite to a single pilot tenant. It does not authorise automatic
production deployment. No real credentials, server IPs, or customer data may be
recorded in this repository — use placeholders only.

Source of truth: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`.

## Pre-deployment gate

| # | Check | How | Blocking |
|---|-------|-----|----------|
| 1 | Source of truth read | Foundation + `docs/PROJECT_RULES.md` reviewed | yes |
| 2 | GO tag chain intact | Sprint 0–14 GO tags present | yes |
| 3 | Sprint 13 release gate | `php artisan release:go-no-go --json` = GO/WATCH | yes |
| 4 | Sprint 14 RC/UAT gate | `php artisan pilot:rc-check --json` = GO/WATCH | yes |
| 5 | Backend tests | `php artisan test` green | yes |
| 6 | Android CI | `assembleDebug` + `testDebugUnitTest` green | yes |
| 7 | DB migration readiness | `php artisan migrate:status` reviewed on dry-run | yes |
| 8 | Backup readiness | Backup/restore runbook rehearsed (dry-run) | yes |
| 9 | Demo tenant readiness | Demo tenant onboarded + seeded (placeholder) | yes |
| 10 | Operator device readiness | `operator-device-readiness.md` completed | yes |
| 11 | Post-deploy smoke readiness | `post-deploy-smoke-checklist.md` ready | yes |
| 12 | Rollback readiness | `pilot-rollback-checklist.md` reviewed | yes |
| 13 | No secrets / forbidden files | Security scan clean | yes |

## Automated gate

Run from `backend/`:

```bash
php artisan pilot:deployment-check --json
```

- `pilot.deployment_docs` — all pilot deployment/field docs exist.
- `pilot.release_docs` — Sprint 13 release docs exist.
- `pilot.rc_docs` — Sprint 14 RC/UAT docs exist.
- `pilot.commands` — required Artisan commands registered.
- `pilot.services` — release + pilot services available.
- `pilot.android_release_readiness` — Android readiness script present.
- `pilot.release_gate` — folds in Sprint 13 release gate.
- `pilot.rc_gate` — folds in Sprint 14 RC/UAT gate.
- `pilot.field_trial` — folds in field trial evidence.

## Deployment GO / WATCH / NO-GO criteria

- **GO** — every gate PASS; no open BLOCKER/CRITICAL field issue.
- **WATCH** — only non-critical warnings; documented risk + follow-up.
- **NO-GO** — any blocking gate FAIL, or any open BLOCKER/CRITICAL field issue.

## Non-negotiable rules

- No automatic production deployment in Sprint 15.
- No signing keys, keystore, APK, or AAB committed.
- No `.env`, database dumps, or real secrets committed.
- Rollback must remain available at all times (see rollback checklist).
