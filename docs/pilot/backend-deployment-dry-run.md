# Backend Deployment Dry-Run

Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation.

A **dry-run rehearsal** of the backend deployment steps. It is executed against a
staging/dry-run target only. Do not run against production automatically and do
not record real server credentials here — use placeholders.

Target host: `STAGING_HOST_PLACEHOLDER` (SSH user `DEPLOY_USER_PLACEHOLDER`).

## Dry-run steps

| # | Step | Command (placeholder) | Evidence |
|---|------|-----------------------|----------|
| 1 | Fetch code | `git fetch --all && git checkout <candidate>` | commit SHA noted |
| 2 | Pull candidate | `git pull --ff-only` | fast-forward only |
| 3 | Install deps | `composer install --no-dev --optimize-autoloader` | exit 0 |
| 4 | Migration status | `php artisan migrate:status` | pending list reviewed |
| 5 | Migrate (dry-run) | `php artisan migrate --pretend` | SQL reviewed |
| 6 | Cache clear | `php artisan optimize:clear` | caches cleared |
| 7 | Cache rebuild | `php artisan config:cache && php artisan route:cache` | caches built |
| 8 | Readiness check | `php artisan production:readiness-check --json` | GO/WATCH |
| 9 | Release gate | `php artisan release:go-no-go --json` | GO/WATCH |

## Rollback preconditions (must be true before deploy)

- Database backup captured and verified (see `../release/backup-restore-runbook.md`).
- Previous release commit/tag recorded: `PREVIOUS_RELEASE_PLACEHOLDER`.
- Rollback checklist reviewed: `pilot-rollback-checklist.md`.

## Evidence placeholders

- `production:readiness-check` output: `READINESS_JSON_PLACEHOLDER`
- `release:go-no-go` output: `GO_NO_GO_JSON_PLACEHOLDER`

## Rules

- Placeholders only; no real server IP, password, or token.
- No automatic real deploy from this document.
- Migrations reviewed with `--pretend` before any real apply.
