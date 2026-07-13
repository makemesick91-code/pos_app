# UIX-7 — Android Cashier Experience Remediation: Deployment Evidence & GO/NO-GO

All values below are observed, not placeholders. The GO decision at the bottom is
**NO-GO pending operator on-device runtime verification** — deliberately, because
this build environment has no Android SDK/emulator/device (JDK 25), so the
authenticated on-device runtime evidence required by UIX7-R039/R044 cannot be
captured here. It is not fabricated.

## Release identity
| Field | Value |
|---|---|
| Sprint | UIX-7 — Android Cashier Experience Remediation |
| Branch | `feature/uix-7-android-cashier-experience-remediation` |
| Code PR | #54 |
| Code merge commit (final release commit) | `76facbe` |
| Baseline (UIX-6 GO) | `ce84477` (tag `uix-6-support-observability-incident-console-go` still peels to `ce84477`) |
| App | `com.aishtech.poslite`, versionName `0.1.0`, versionCode `1`, minSdk 26 / target 35 |

## 1. Authoritative CI — GREEN
- PR #54 head `7e493ab`: **330 checks passed, 0 failed, 0 pending** across all
  sprint/uix workflows (each runs on every PR).
- UIX-7 workflow (`uix7-ci.yml`) both event runs `completed / success`:
  - `UIX-7 Android cashier foundation + design gate` — foundation gate + design
    gate (chains UIX-6→UIX-1) + rule-id grep (UIX7-R001..R044).
  - `UIX-7 Android build & unit tests` — JDK 21 `assembleDebug` + `assembleRelease`
    + `testDebugUnitTest` (incl. `RupiahMoneyTest` and the offline-sync orphan
    recovery test).
- Local gate runs (advisory): application foundation gate PASS, UIX-7 Android
  design gate PASS.

## 2. Merge & source equality — EXACT MATCH
| Location | Commit |
|---|---|
| local `main` | `76facbe` |
| `origin/main` | `76facbe` |
| VPS `/var/www/aish-pos` HEAD | `76facbe` |

`ce84477 → 76facbe` (merge of PR #54). Diff touches only `android/`, `docs/`,
`.claude/`, `scripts/`, `.github/`, `CLAUDE.md`, `AGENTS.md` — **0 backend files
changed**, so no backend deploy / composer / migration / cache rebuild was
required and the Aish PHP runtime is byte-identical to the UIX-6 build.

## 3. VPS runtime verification (backend/host) — HEALTHY
- Aish services `active`: `php8.5-fpm`, `aish-pos-queue-worker`, `nginx`.
- Health: `http://127.0.0.1:8080/health/live` = 200; `https://aishpos.online/health/live`
  = 200; `https://aishpos.online/health/ready` = 200; `http://aishpos.online → 301`
  → `https://aishpos.online` (HTTPS + redirect active, UIX7-R030-transport).
- Runtime ownership preserved (UIX7-R041): `backend/storage/framework` and
  `backend/bootstrap/cache` = `www-data:www-data`.

## 4. DaengtisiaMS non-regression — UNCHANGED
- DMS HEAD `8b0bb6a` (baseline `8b0bb6a`) — unchanged before and after.
- `php8.3-fpm` active, PHP 8.3.6, `nginx` active. Host healthy (swap 0, mem ~0.8/7.9 GB).
- UIX-7 changed no php8.3 / `daeng` / DMS nginx/systemd/DB resource. DMS is
  fully non-regressed (UIX7-R043).

## 5. Pilot artifact traceability (UIX7-R038)
Built by CI (`uix7-ci.yml` → `android-build-test`, JDK 21) and uploaded as
artifact `uix7-pilot-apks-<sha>`. Hashes below are from the evidence-PR #55
`uix7-ci` run (GitHub `pull_request` test-merge `source_commit ea55e7a`, whose
tree equals the post-merge `main`); the same build is reproduced by `main`'s
`uix7-ci` after this PR merges.
```
package_id    : com.aishtech.poslite
version_name  : 0.1.0
version_code  : 1
release APK   : app-release-unsigned.apk
  sha256      : a3a594ef0990fe4460b7d326d23d9e995a7b6fe1db8a5620843a902f18ec2103
debug APK     : app-debug.apk (debug-signed, installable)
  sha256      : e14fe3613b167ea90a6f156b9026aaabce1b7f933ddfd27c71641393aca3ad3f
```
The operator's installed/signed pilot APK hash is captured separately in the
on-device checklist §Artifact (that is the artifact GO ultimately certifies).

## 6. On-device runtime verification — DEFERRED (operator)
Required by UIX7-R039/R044 and GO items 45–53: install an APK on an approved
device/emulator and verify, against `https://aishpos.online` with synthetic data:
online sale; offline sale → process-kill → relaunch (durability) → reconnect →
sync; orphaned in-flight recovery (the fixed SYNCING bug) with no duplicate;
duplicate-submit protection; QRIS lifecycle truthfulness (sandbox, no real
charge); receipt/history consistency; state restoration; accessibility &
performance spot-check; then synthetic-data cleanup.

Procedure: `docs/deployment/uix-7-android-runtime-verification-checklist.md`.

**This evidence is not yet captured** (no device in the build environment), so it
is honestly recorded as pending rather than fabricated.

## GO / NO-GO decision
**NO-GO (deferred).** Everything reachable without a device is green:
authoritative CI (330/0), merge to `main`, exact local/origin/VPS match at
`76facbe`, backend/HTTPS runtime healthy, runtime ownership preserved, and
DaengtisiaMS non-regressed. The single open release blocker is the authenticated
on-device runtime verification (UIX7-R039). Per rule 90 / UIX7-R044, absence of
that observed evidence is NO-GO, so the annotated GO tag
`uix-7-android-cashier-experience-remediation-go` is **not** created until the
operator completes the checklist and the evidence is recorded here. Previous GO
tags remain immutable.
