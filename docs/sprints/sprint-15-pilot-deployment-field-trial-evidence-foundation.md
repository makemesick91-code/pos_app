# Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation

## Objective

Establish the pilot deployment and field trial evidence foundation on top of the
Sprint 14 RC/UAT-ready app: a non-destructive, secret-safe, evidence-backed path
from a release candidate to a monitored pilot field trial with a GO/WATCH/NO-GO
decision.

Flow: RC/UAT Ready App → Pilot Deployment Checklist → Backend Deployment Dry-Run
Evidence → Android RC Artifact Evidence → Field Trial Checklist → Daily
Monitoring → Field Issue Register → GO/WATCH/NO-GO Field Trial Report.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md`
- Sprint 0–14 evidence docs under `docs/sprints/`.

## Previous Sprint Foundation Lock

Sprint 0–14 rules remain intact in `docs/PROJECT_RULES.md`. The Foundation Lock
Index now includes this Sprint 15 document.

## Scope

In scope: pilot deployment/field trial evidence governance — services, commands,
docs, tests, smoke, CI gate, rules lock.

Out of scope: new business modules, new cashier/QRIS/inventory/report features,
real production deploy automation with credentials, Play Store deployment,
signing key/keystore/APK/AAB commits, Android admin/deployment/UAT UI.

## Graphify Summary

- Reused Sprint 13 `ReleaseGateService` / `ProductionReadinessService` and
  Sprint 14 `PilotReleaseCandidateService` / `OperatorUatSummaryService`
  patterns (GO/WATCH/NO-GO, `checks[]`, repo-root doc resolution).
- Added `App\Services\Pilot\PilotDeploymentReadinessService` and
  `App\Services\Pilot\FieldTrialEvidenceService`.
- Added `pilot:deployment-check` and `pilot:field-trial-summary` commands.
- Added `config/pilot_deployment.php`; extended `config/pos_foundation.php`.
- Added pilot deployment/field docs under `docs/pilot/`.
- Added `scripts/sprint15_smoke.sh` and `.github/workflows/sprint15-ci.yml`
  (adds a dedicated pilot deployment/field gate job).

## Backend Implementation

Located under `backend/`, reusing Sprint 10–14 middleware, admin, onboarding,
subscription/device, cash, QRIS, receipt, offline sync, inventory, reports,
closing, release, and RC/UAT foundations without modification.

## PilotDeploymentReadinessService

`backend/app/Services/Pilot/PilotDeploymentReadinessService.php` — verifies pilot
deployment/field docs, Sprint 13 release docs, Sprint 14 RC/UAT docs, required
commands, release/pilot services, the Android release readiness script, and
folds in the release gate, RC/UAT gate, and field trial evidence decisions.
Returns `{decision, checks[], field_summary}`. Critical FAIL => NO-GO;
non-critical WARN => WATCH; all PASS => GO. Runs no Gradle and no real deploy.

## FieldTrialEvidenceService

`backend/app/Services/Pilot/FieldTrialEvidenceService.php` — provides the
canonical field trial evidence categories and evaluates an optional structured
field trial result file. Open BLOCKER/CRITICAL issue => NO-GO; open MAJOR =>
WATCH; otherwise GO. Never reads secrets or real customer data.

## Artisan Commands

- `pilot:deployment-check {--json} {--strict}` — aggregate deployment decision.
- `pilot:field-trial-summary {--json} {--strict}` — field trial evidence
  decision + category count.

Exit codes: `0` GO/WATCH (unless `--strict` on WATCH), `1` NO-GO / strict WATCH.
Neither command prints secrets, requires real production data, executes Android
Gradle, or performs a deployment.

## Pilot Deployment Checklist

`docs/pilot/pilot-deployment-checklist.md` — pre-deployment gate, automated gate,
and GO/WATCH/NO-GO criteria.

## Backend Deployment Dry-Run Evidence

`docs/pilot/backend-deployment-dry-run.md` — placeholder dry-run rehearsal
(git pull, composer install, migration status/`--pretend`, cache rebuild,
readiness + go/no-go evidence, rollback preconditions). No real credentials.

## Android RC Artifact Handling

`docs/pilot/android-rc-artifact-handling.md` — CI build evidence, artifact
transport without committing binaries/signing keys, install + rollback checklist.

## Operator Device Readiness

`docs/pilot/operator-device-readiness.md` — device, OS, storage, battery,
network, printer, install, registration, subscription/device, offline checks.

## Demo Tenant Pilot Setup Evidence

`docs/pilot/demo-tenant-pilot-setup-evidence.md` — onboarding, store, users,
subscription, device, demo products, opening inventory, report/closing, reset
guard. Placeholders only.

## Post-Deploy Smoke Checklist

`docs/pilot/post-deploy-smoke-checklist.md` — SMK-01..SMK-15 covering health,
auth, tenant context, product sync, stock, cash sale, QRIS, receipt, printer,
offline cash, offline sync, daily report, closing, subscription/device, admin
onboarding.

## Rollback Checklist

`docs/pilot/pilot-rollback-checklist.md` — triggers, communication, Android +
backend rollback, backup/restore reference, non-destructive warning, issue
register update.

## Daily Pilot Monitoring Checklist

`docs/pilot/daily-pilot-monitoring-checklist.md` — daily login, sales, payment,
sync queue, failed sync, receipt/printer, inventory anomaly, closing,
device/subscription, open issue review.

## Field Issue Register

`docs/pilot/field-issue-register.md` — severity + status taxonomy, gating rules,
table template, optional structured export.

## Field Trial GO/WATCH/NO-GO Report

`docs/pilot/field-trial-go-watch-no-go-report.md` — candidate, gate results, open
issue summary, readiness, risk notes, decision + approver placeholders.

## Android CI Evidence

Android remains the authoritative build gate. `sprint15-ci.yml` runs
`:app:assembleDebug` and `:app:testDebugUnitTest` on JDK 21. No Android runtime
change was made; package `com.aishtech.poslite`, minSdk 26, targetSdk 35 intact.

## Release Gate Evidence

`production:readiness-check` and `release:go-no-go` still run and are folded into
the deployment decision.

## RC/UAT Gate Evidence

`pilot:rc-check` and `pilot:uat-summary` still run and are folded into the
deployment decision.

## No Business Feature Expansion Decision

No new business module, cashier workflow, QRIS provider behavior, inventory, or
report feature was added. This sprint is deployment/field-trial governance only.

## No Auto Production Deploy Decision

No automatic production deployment was implemented. Deployment steps are
placeholder dry-runs; the services perform no deploy.

## Application Rules Update

`docs/PROJECT_RULES.md` gains the Sprint 15 Pilot Deployment & Field Trial
Evidence Foundation Runtime Rule; the Foundation Lock Index lists this document.
`config/pos_foundation.php` lists `sprint_15` and the new gate flags.

## Testing Evidence

New tests under `backend/tests/Feature/`:

- `PilotDeploymentReadinessServiceTest`
- `PilotDeploymentCheckCommandTest`
- `FieldTrialEvidenceServiceTest`
- `PilotFieldTrialSummaryCommandTest`
- `PilotDeploymentSecurityScanTest`
- `PilotDeploymentRegressionRouteTest`

## Backend Compatibility Evidence

`PilotDeploymentRegressionRouteTest` asserts the Sprint 0–14 route surface and
release/RC/UAT/deployment commands remain registered. Existing Sprint 0–14 tests
continue to pass.

## Validation Commands

```bash
bash scripts/sprint15_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan pilot:rc-check --json
cd backend && php artisan pilot:uat-summary --json
cd backend && php artisan pilot:deployment-check --json
cd backend && php artisan pilot:field-trial-summary --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Validation Results

- Recorded on the PR / CI run (`sprint15-ci`). Android build/test is the
  authoritative gate and must be green before the GO tag.

## GO Criteria

Services + commands present; all pilot deployment/field docs present; commands
support `--json` and are secret-safe; Sprint 13 release + Sprint 14 RC/UAT
commands still run; Android release readiness passes; `sprint15-ci` runs smoke,
backend tests, release gate, RC/UAT gate, deployment/field gate, and Android
build/test; no business feature expansion; no auto production deploy; no
forbidden files; PR merged; GO tag exact-match to main HEAD.

## No-Go Checks

Missing service/command/doc; commands print secrets; Sprint 13/14 commands
broken; Android readiness broken; CI missing deployment/field or Android gate; CI
red; backend tests fail; smoke fails; auto production deploy added; APK/AAB/
signing key/secret committed; new business feature added; package != 
`com.aishtech.poslite`; minSdk != 26; targetSdk != 35; working tree not clean.

## Follow-up for Sprint 16

- Post-pilot stabilization: convert field issue register findings into fixes.
- Optional guarded production deployment automation with approvals + evidence.
