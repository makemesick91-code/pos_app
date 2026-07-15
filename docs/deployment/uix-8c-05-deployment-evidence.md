# UIX-8C-05 — Deployment & Runtime-Closure Evidence

- Sprint: UIX-8C-05 — Premium Cash Payment, Offline Queue & Sync Recovery UX
- Package: `com.aishtech.poslite`
- Baseline: `origin/main` = `f6045b4` (UIX-8C-04 final)
- UIX-8C-04 anchor: `5063eb4`, tag `uix-8c-04-offline-cash-durability-idempotent-recovery-go`

## Purpose

Record the authoritative deployment and closure evidence for UIX-8C-05. All
deploy-time facts below are finalized from the actual merge + deployment.

## Source & CI provenance

| Fact | Value |
|------|-------|
| Baseline SHA | `f6045b4` |
| Candidate SHA | `02bf4b7` (implementation branch tip) |
| Runtime source anchor | `8505ab5` (implementation merge commit) |
| Authoritative CI run id | `29377726367` — **SUCCESS** on exact SHA `02bf4b7` |
| Implementation PR # | [#75](https://github.com/makemesick91-code/pos_app/pull/75) |
| Implementation merge commit | `8505ab5` |
| Android test totals | `testDebug/Pilot/Release` + `lintDebug/Pilot/Release` + `assembleDebug/Pilot/Release` all **BUILD SUCCESSFUL**; 6 new UIX-8C-05 unit-test classes + reused `CashierCheckoutFallbackTest` |
| Backend test totals | **1522 / 1522** passed (44,763 assertions), incl. new `PaymentSyncUxIdempotencyRegressionTest` (2 tests, 26 assertions) |
| Gate totals | new `uix8c_payment_offline_sync_ux_gate.sh` **PASS** + 13-case self-test **PASS**; foundation / design-system / cashier-cart / offline-cash / foundation-verifier / CICD-CTRL-2 / classifier + `uix1..uix7` design gates all **PASS** |

## Deploy & synchronization

| Fact | Value |
|------|-------|
| Deploy commit | `8505ab5` (git `merge --ff-only origin/main`; no migration/composer/npm/cache step — Android/docs/scripts/rules + backend-test-only change) |
| Local / origin / VPS exact-match | all `8505ab5` ✅ |
| Health (root, `/live`, `/ready`) | `200` / `200` / `200` (https://aishpos.online) |
| Services state (before/after) | `php8.5-fpm`, `nginx`, `postgresql`, `aish-pos-queue-worker` — **active** before and after |
| Runtime file ownership (`www-data:www-data`) | `backend/storage/framework` + `backend/bootstrap/cache` = `www-data:www-data` ✅ |
| Root-owned runtime files count | `0` |
| Migrations (pending/run) | `0` pending / `0` run (UIX-8C-05 adds no migration) |

## DaengtisiaMS non-regression bracket

| Fact | Value |
|------|-------|
| DMS HEAD before | `8b0bb6a` |
| DMS HEAD after | `8b0bb6a` (unchanged ✅) |
| DMS worktree clean | yes ✅ |
| DMS services (php8.3-fpm, nginx, PostgreSQL, `daengtisiams-queue-worker`) active | all **active** ✅ |

DMS non-regression: **PASS** — HEAD unchanged, worktree clean, all DMS services
active. No Aish deploy step touched php8.3, the `daeng` user, or DMS
nginx/systemd/database.

## Release tags & closure

| Fact | Value |
|------|-------|
| Final sprint tag | `uix-8c-05-premium-cash-payment-offline-sync-recovery-go` (annotated; peels to the final evidence commit) |
| Final evidence commit | the merge commit of this post-merge evidence PR into `main`; the annotated sprint tag is created on it and peels to it |

The sprint tag records **source remediation + automated verification only**; it
never asserts UIX-7 or UIX-8 runtime closure. This sprint runs **no physical
campaign**. Historical failed physical run `run-97fbb64-2af94aa` (R11 FAIL, R18
FAIL, R01 PENDING) stays immutable.

## Evidence-only diff constraint

The evidence-closure commit differs from the tested candidate `02bf4b7` (merged
as `8505ab5`) **only** by this evidence-only diff. It does not change Android or
backend source, Room/schema, dependencies, workflows, rules, gates, tests,
config, or runtime manifests. The candidate is an ancestor of the
evidence-closure commit.

## Mandatory closure statement

```
UIX-8C-05 premium payment and synchronization UX implementation PASS.
UIX-8C-04 transaction authority was reused without creating a second checkout,
offline persistence, clientReference, WorkManager, or backend sale path.
Historical R11 remains FAIL for the old physical APK.
Fresh physical R11 and payment/sync UX validation remains mandatory after final
Android code freeze and final pilot APK generation.
UIX-7 and UIX-8 remain GO deferred.
```

## Terminal status

- UIX-7 = `NO-GO — GO DEFERRED`
- UIX-8 = `IMPLEMENTATION COMPLETE — GO DEFERRED`
- Absence of proof remains NO-GO.
