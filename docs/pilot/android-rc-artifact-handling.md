# Android RC Artifact Handling

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

Defines how the Android release candidate artifact is produced, transported, and
installed for a pilot **without committing any binary or signing material** to
the repository. Android CI is the authoritative build gate.

Package: `com.aishtech.poslite` · minSdk 26 · targetSdk 35.

## Build evidence (CI)

| # | Item | Source | Evidence |
|---|------|--------|----------|
| 1 | Debug build | CI `:app:assembleDebug` | run URL + green status |
| 2 | Unit tests | CI `:app:testDebugUnitTest` | run URL + green status |
| 3 | Release readiness | `scripts/android_release_readiness.sh` | script pass |
| 4 | versionCode | `android/app/build.gradle.kts` | value recorded |
| 5 | versionName | `android/app/build.gradle.kts` | value recorded |

## Artifact handling

- The pilot APK is downloaded from the CI build artifacts, **not** committed.
- Release **signing is not included** in this foundation; the pilot uses the CI
  debug artifact or an externally signed build managed outside the repo.
- **No keystore, `.jks`, or signing key is committed.**
- No APK/AAB is committed to git.
- No Play Store deployment is performed in Sprint 15.

## Installation checklist (pilot device)

1. Confirm device meets `operator-device-readiness.md`.
2. Enable install from trusted source for the pilot session only.
3. Install the CI artifact APK.
4. Launch, confirm login + tenant context.
5. Record `versionCode` / `versionName` in the evidence pack.

## Rollback / uninstall checklist

1. Uninstall the pilot APK.
2. Reinstall the previous approved build if rolling back.
3. Record action in `field-issue-register.md` and `pilot-rollback-checklist.md`.

## Rules

- Do not commit APK/AAB.
- Do not commit signing keys/keystore.
- Do not require Play Store deployment.
