# Release GO / NO-GO Runbook

Sprint 13 — Production Readiness & Release Hardening Foundation.

Source of truth: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` and `docs/PROJECT_RULES.md`.

## Required commands (backend)

Run from `backend/`:

```bash
php artisan production:readiness-check --json
php artisan release:go-no-go --json
php artisan test
```

- `production:readiness-check` — env/runtime safety, never prints secrets.
- `release:go-no-go` — aggregates readiness + docs/routes/commands/forbidden-files into GO/WATCH/NO-GO.

These commands do **not** run Android Gradle. CI owns the Android build gate.

## Required CI checks (Sprint 13 workflow)

The `sprint13-ci` workflow must be **green** before a GO tag:

1. `foundation-and-smoke` — `scripts/sprint13_smoke.sh` + `scripts/android_release_readiness.sh`.
2. `backend-tests` — `composer validate --strict` + `php artisan test` (PHP 8.5).
3. `backend-release-gate` — `production:readiness-check --json` + `release:go-no-go --json`.
4. `android-build-test` — `:app:assembleDebug` + `:app:testDebugUnitTest` (JDK 21).

Android CI is the authoritative build gate (local Android build may be unavailable).

## GO / WATCH / NO-GO criteria

| Decision | Meaning | Tag allowed? |
| --- | --- | --- |
| **GO** | All required checks pass. | Yes |
| **WATCH** | Only non-critical warnings (e.g. `APP_DEBUG` on in a non-prod env). | Yes, with the warnings acknowledged and CI green. |
| **NO-GO** | Any critical failure (missing doc/route/command, forbidden file tracked, dangerous env, missing `APP_KEY`). | No |

## Tag policy

- Tag name: `sprint-13-production-readiness-release-hardening-foundation-go`.
- Tag only after the change is merged to `main` and CI is green on the merged commit.
- The tag must be an exact match to `main` HEAD (`git describe --tags --exact-match HEAD`).
- Never overwrite an existing tag. If a tag exists but does not point to the latest `main`, stop and report.

## Evidence policy

- Store validation output and CI run details in the sprint evidence doc
  (`docs/sprints/sprint-13-production-readiness-release-hardening-foundation.md`).
- Record the GitHub Actions run id, status, conclusion, and headSha.

## Rollback decision checklist

- [ ] Is the failure user-facing or data-affecting? If yes, prefer rollback.
- [ ] Is a verified pre-release backup available? (see `backup-restore-runbook.md`)
- [ ] Has the release owner given written go-ahead to roll back?
- [ ] After rollback, re-run `production:readiness-check` and smoke tests.
- [ ] Record the decision and outcome.
