# Production Readiness Checklist

Sprint 13 — Production Readiness & Release Hardening Foundation.

Source of truth: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` and `docs/PROJECT_RULES.md`.

This checklist is enforced (where automatable) by:

```bash
cd backend && php artisan production:readiness-check --json
cd backend && php artisan release:go-no-go --json
```

No command in this project prints secret values. Sensitive checks report presence/shape only.

## Environment safety

- [ ] `APP_ENV` is set to the intended environment (`production` for prod).
- [ ] `APP_DEBUG=false` in production-like environments (fails the readiness check otherwise).
- [ ] `APP_KEY` is set (`php artisan key:generate`). The value is never printed by any tool.
- [ ] `.env` is **not** committed to git.

## Database & migrations

- [ ] Database connection is reachable (`database.connection` PASS).
- [ ] Migration repository exists and all migrations are applied (`migrations.status` PASS).
- [ ] A verified backup exists before running migrations in production (see `backup-restore-runbook.md`).

## Cache / session / queue

- [ ] Cache driver configured (`cache.default`).
- [ ] Session driver configured (`session.driver`).
- [ ] Queue connection configured (`queue.default`); avoid `sync` in production.

## Storage / logs

- [ ] `storage/app` is writable.
- [ ] `storage/logs` is writable.
- [ ] Backup directory (`storage/app/backups`) is writable or creatable.

## Payment gateway secret handling

- [ ] Real payment gateway credentials live **only** in the environment, never in the database, never in Android, never in git.
- [ ] The `fake` QRIS provider is used for local/CI; real providers are enabled only with live credentials supplied at runtime.

## Subscription / device enforcement (Sprint 10)

- [ ] `subscription.active` and `device.registered` middleware remain wired to business APIs.
- [ ] `api/v1/subscription/status` and `api/v1/devices` remain reachable.

## Admin / onboarding enforcement (Sprints 11–12)

- [ ] `platform.admin` guards all `api/v1/admin/*` routes.
- [ ] Onboarding is platform-admin-only, idempotent, and audit-logged.
- [ ] No public self-service signup, no real billing charge, no tenant impersonation.

## Android build gate

- [ ] Package remains `com.aishtech.poslite`, `minSdk = 26`, `targetSdk = 35`.
- [ ] `versionCode` and `versionName` present in `android/app/build.gradle.kts`.
- [ ] No Android `AdminActivity` / `OnboardingActivity` / payment secrets.
- [ ] CI `assembleDebug` and `testDebugUnitTest` are green (authoritative build gate).
- [ ] `bash scripts/android_release_readiness.sh` passes.

## Forbidden files (must not be committed)

- [ ] No `.env`, `*.apk`, `*.aab`, `*.keystore`, `*.jks`, `database.sqlite`.
- [ ] Only allowed committed binary: `android/gradle/wrapper/gradle-wrapper.jar`.
