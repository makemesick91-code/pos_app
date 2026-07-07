# Sprint 13 — Production Readiness & Release Hardening Foundation

## Objective

Establish the production readiness and release hardening foundation for Aish POS
Lite: environment safety validation, a backend release gate, backup/restore and
release runbooks, and an Android release readiness gate — all wired into a
Sprint 13 CI release gate. **No new business features**; this sprint hardens
release, it does not expand scope. No automatic production deployment is added.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (sections 8, 9, 10, 12, 14, 15, 16, 17, 21, 22, 25, 26)
- `docs/PROJECT_RULES.md`
- Sprint 0–12 evidence docs under `docs/sprints/`

This is a multi-tenant Android POS SaaS, not a single-store POS.

## Previous Sprint Foundation Lock

Sprints 0–12 remain intact and authoritative. Sprint 13 adds a runtime rule and
foundation flags without removing any prior rule. The Foundation Lock Index in
`docs/PROJECT_RULES.md` now lists Sprint 13.

## Scope

In scope: production readiness command, release GO/NO-GO command, release gate
services, backup readiness service, release config, release docs/runbooks,
Android release readiness script, version/package/SDK governance checks, Sprint
13 CI release gate, Sprint 13 rules lock, tests.

Out of scope: new business modules, advanced reporting, real production deploy
automation, real signing keys, Play Store deployment, billing charges, tenant
self-service, admin/onboarding web or Android UI, destructive DB reset.

## Graphify Summary

- Release readiness/gate is a new `App\Services\Release\*` namespace consumed by
  two Artisan commands; it reads existing config (`app`, `database`, `cache`,
  `session`, `queue`, `payment_gateway`, `pos_foundation`) and the existing route
  table — it does not alter business behavior.
- Docs live at repo root (`docs/`) while the Laravel app lives in `backend/`; the
  release gate resolves required-doc paths against `base_path('..')`.
- Android build remains CI-gated (Gradle wrapper committed since Sprint 6). Sprint
  13 adds a static release readiness script; it does not require signing keys.
- GO tag depends on: merged to `main`, Sprint 13 CI green (smoke + backend tests +
  release gate + Android assembleDebug/testDebugUnitTest), clean working tree.

## Backend Implementation

- `backend/config/release_readiness.php` — required docs/routes/commands and
  forbidden file patterns (no secrets).
- `backend/config/pos_foundation.php` — Sprint 13 flags + `sprint_13` entry.

### Production Readiness Service

`backend/app/Services/Release/ProductionReadinessService.php` — structured
PASS/WARN/FAIL checks for app env/key/debug, database, migrations, cache,
session, queue, storage, logs, payment gateway secret presence, and foundation
lock. Never emits secret values; sensitive checks are flagged and redacted.
`APP_DEBUG=true` in a production-like env is FAIL; missing `APP_KEY` is FAIL.

### Release Gate Service

`backend/app/Services/Release/ReleaseGateService.php` — aggregates readiness with
required-docs, required-routes, required-commands, and forbidden-files (checked
against `git ls-files`) into a GO / WATCH / NO-GO decision. Runs no Android
Gradle.

`backend/app/Services/Release/BackupReadinessService.php` — validates backup
directory/storage writability and exposes credential-free backup/restore
templates.

## Artisan Commands

- `production:readiness-check {--json} {--strict}` — exit 0 on PASS/WATCH, 1 on
  FAIL or strict-with-warnings.
- `release:go-no-go {--json} {--strict}` — exit 0 on GO/WATCH, 1 on NO-GO or
  strict-with-WATCH.

## Android Release Readiness

`scripts/android_release_readiness.sh` — validates gradlew presence/executability,
package `com.aishtech.poslite`, `minSdk = 26`, `targetSdk = 35`, `versionCode`,
`versionName`, no payment secrets, no `AdminActivity`/`OnboardingActivity`, no
committed APK/AAB, and Gradle wrapper files. Does not require signing keys.

Version governance: `android/app/build.gradle.kts` retains `versionCode` /
`versionName`. No real `signingConfig`, keystore, or Play Store config is added.

## Backup/Restore Runbook

`docs/release/backup-restore-runbook.md` — pre-release backup checklist, DB/storage
backup templates, staging restore rehearsal, rollback notes. No real credentials;
no destructive production restore automated.

## Release GO/NO-GO Runbook

`docs/release/release-go-no-go-runbook.md` — required commands, required CI checks,
GO/WATCH/NO-GO criteria, tag policy, evidence policy, rollback checklist.

## CI Release Gate

`.github/workflows/sprint13-ci.yml` jobs:

1. `foundation-and-smoke` — `sprint13_smoke.sh` + `android_release_readiness.sh`.
2. `backend-tests` — `composer validate --strict` + `php artisan test` (PHP 8.5).
3. `backend-release-gate` — `production:readiness-check --json` + `release:go-no-go --json`.
4. `android-build-test` — `:app:assembleDebug` + `:app:testDebugUnitTest` (JDK 21),
   not optional, no `continue-on-error`.

## Security / Secret Redaction

Release commands never print `APP_KEY` or payment gateway secrets (verified by
`ReleaseSecurityScanTest`). Sensitive checks report presence/shape only. No
`.env`, APK/AAB, keystore, or DB file is committed.

## No Auto Production Deploy Decision

Sprint 13 deliberately adds **no** production deployment automation, no VPS
credential handling, and no app signing keys. The CI Android build gate produces
a debug APK only. Production deploy remains a manual, explicitly-approved,
separately-evidenced step (future sprint).

## Application Rules Update

`docs/PROJECT_RULES.md` — added the Sprint 13 Production Readiness & Release
Hardening Foundation Runtime Rule and extended the Foundation Lock Index to
Sprint 13. Sprints 0–12 rules are unchanged.

## Testing Evidence

New backend tests: `ProductionReadinessServiceTest`, `ReleaseGateServiceTest`,
`ProductionReadinessCommandTest`, `ReleaseGoNoGoCommandTest`,
`ReleaseRegressionRouteTest`, `ReleaseSecurityScanTest`. Existing Sprint 0–12
suites remain green.

Validation results are recorded in the "Validation Results" section below.

## Backend Compatibility Evidence

`ReleaseRegressionRouteTest` asserts health/auth/tenant-context/sync/sales/
payments/webhooks/receipt/inventory/reports/closings/subscription/devices/admin/
onboarding routes remain registered. No business controller/route was modified.

## Android CI Evidence

`android-build-test` runs `assembleDebug` and `testDebugUnitTest` on JDK 21. See
the recorded GitHub Actions run in "Validation Results".

## Validation Commands

```bash
bash scripts/sprint13_smoke.sh
bash scripts/android_release_readiness.sh
cd backend && composer validate --strict
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
cd backend && php artisan test
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest
```

## Validation Results

_Filled during execution — see final report / commit history._

- sprint13 smoke: PASS
- android_release_readiness: PASS
- composer validate --strict: PASS
- production:readiness-check --json: ran (no secrets)
- release:go-no-go --json: ran (no secrets)
- backend tests: PASS
- Android assembleDebug (CI): see GitHub Actions run
- Android testDebugUnitTest (CI): see GitHub Actions run

## GO Criteria

See the master checklist in the sprint prompt (items 1–27): foundation locked,
Sprint 0–13 rules present, services + commands exist, secrets redacted,
dangerous-env fails tested, runbooks present, CI runs smoke/backend/release/
Android, no deploy automation, no secrets committed, prior behavior intact, CI
green, GO tag exact-match to `main`.

## No-Go Checks

Any of: foundation/rules unreadable, prior rules lost, missing Sprint 13 rule,
missing services/commands, secret leakage, dangerous env not failing, missing
runbooks, missing Android readiness, CI not running required jobs, CI red, tests
failing, deploy automation added, secrets/keystore committed, business API
regression, wrong package/SDK, forbidden files committed, dirty working tree.

## Follow-up for Sprint 14

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation: build on this gate
with a signed release-candidate flow (signing handled via secure CI secrets, not
committed), operator UAT checklist, and staged rollout evidence.
