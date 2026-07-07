# Pilot Rollback Checklist

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Non-destructive rollback plan for a pilot. Rollback must always be available.
Placeholders only.

## Rollback trigger conditions

- Any open **BLOCKER** or **CRITICAL** field issue during the trial.
- Backend health failing post-deploy (SMK-01).
- Auth / tenant context / cash sale / offline sync failing (blocking smoke).
- Data integrity concern on the demo tenant.

## Communication checklist

1. Notify pilot operator and stakeholders (`STAKEHOLDER_PLACEHOLDER`).
2. Record decision + timestamp in `field-issue-register.md`.
3. Freeze new sales on the pilot device.

## Android rollback

1. Uninstall the current pilot APK.
2. Reinstall the previous approved build (from CI artifacts).
3. Confirm login + tenant context on the restored build.
4. Record `versionCode`/`versionName` restored.

## Backend rollback decision

| # | Step | Note |
|---|------|------|
| 1 | Identify previous release | `PREVIOUS_RELEASE_PLACEHOLDER` |
| 2 | Decide code vs data rollback | prefer code-only if data intact |
| 3 | Restore code | checkout previous release commit/tag |
| 4 | Database restore (only if required) | see runbook below |
| 5 | Re-run smoke | `post-deploy-smoke-checklist.md` |

## Database backup / restore reference

- Follow `../release/backup-restore-runbook.md`.
- **Non-destructive warning:** never drop/truncate production or shared tables.
  Restore into a verified backup target; confirm before switching.

## After rollback

- Update `field-issue-register.md` (status, fix sprint/PR).
- Update `field-trial-go-watch-no-go-report.md` with the rollback outcome.

## Rules

- Rollback must not destroy tenant data.
- No automatic destructive database reset.
