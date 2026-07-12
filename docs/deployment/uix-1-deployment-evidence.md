# UIX-1 — Deployment Evidence & Plan

**Status:** implementation + PR stage complete locally; **live deploy, merge-to-main, and GO tag are gated**
on: (1) required CI green on the PR, (2) explicit operator go-ahead for the shared-VPS production step.
No GO tag is created before deploy + smoke actually pass. This document is the runbook + evidence shell.

## Change classification

Additive UI-foundation change: Android resource XML (`colors`/`dimens`/`styles`/`themes`/`strings`), one
Kotlin adapter refactor (hex → token resource), web CSS token file, public-website palette re-point, docs,
governance rules, gate script, CI workflow. **No database migrations. No route changes. No new screens.**
Therefore: no `migrate` step required; queue/scheduler unaffected.

## Pre-deploy safety (operator, on VPS)

1. Record baselines: `aish-pos` HEAD, `asia-dental-lab-v2` (DaengtisiaMS) HEAD + working tree clean.
2. Backups: Aish POS DB, DaengtisiaMS safety, Nginx config, runtime config — verify before proceeding.
3. Service health: `nginx -t`, `php-fpm8.5 -t`, `php-fpm8.3 -t`; status of nginx, php8.3-fpm, php8.5-fpm,
   postgresql, `aish-pos-queue-worker`.
4. Resource baseline: `free -h`, `swapon --show`, `df -h`, `uptime`.

## Deploy (Aish POS only)

```
cd /var/www/aish-pos && git fetch origin && git switch main && git pull --ff-only origin main
# expect HEAD == UIX1_MERGE_COMMIT, tree clean
cd backend && composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
# Frontend build NOT required for this change: the public-website palette change is inline Blade and does
# not depend on the Vite bundle; the repo commits no package-lock.json. Only run `npm install && npm run build`
# if the current deploy already builds frontend assets.
# NO artisan migrate (no migrations in this change)
php8.5 artisan optimize:clear && php8.5 artisan config:cache && php8.5 artisan route:cache && php8.5 artisan view:cache
sudo systemctl restart aish-pos-queue-worker
sudo php-fpm8.5 -t && sudo nginx -t && sudo systemctl reload php8.5-fpm && sudo systemctl reload nginx
```

**Do NOT:** restart all services, reboot VPS, change firewall, open 8080 publicly, touch PHP 8.3, run
`apt upgrade`.

## Runtime smoke (expected)

- `GET http://127.0.0.1:8080/` → 200 / expected redirect
- `GET /health/live` → 200 · `GET /health/ready` → 200
- `curl -I /.env` and `/storage/logs/laravel.log` → 403/404
- `php8.5 artisan about`, `migrate:status`, `schedule:list`, `pilot:runtime-storage-status` nominal
- Public-website pages render with foundation palette; no missing assets / broken CSS.

## Android artifact

Built from `UIX1_MERGE_COMMIT` via existing release process on CI (JDK 21): `assembleDebug` (pilot) and
`assembleRelease` only if signing already configured. No signing secret is created or exposed. Local
build is not possible here (JDK 25); CI is the build gate (repo convention).

## DaengtisiaMS regression (must be unchanged)

`asia-dental-lab-v2` HEAD unchanged, tree clean, `php8.3 -v` 8.3.x, `php-fpm8.3 -t` ok, HTTP + DB
(`asia_dental_lab_pilot` / `dental_pilot`) nominal, RAM/swap/load comparable. **Any regression → NO-GO.**

## Rollback

`PRE_UIX1_DEPLOY_COMMIT=<record before deploy>` → `git checkout` it in `/var/www/aish-pos`, re-run
composer/npm/build + cache commands + `restart aish-pos-queue-worker` + reload php8.5-fpm/nginx. No DB
rollback needed (no migrations). DaengtisiaMS DB never touched. Android: redistribute prior pilot artifact.

## Redaction

Operator IP, `APP_KEY`, tokens, signing secrets, DB credentials, and private server details are **not** in
this repo doc. Absolute operator paths are referenced generically ("operator-provided handoff package").

## GO gate & tag

Create annotated tag `uix-1-complete-handoff-implementation-go` on `UIX1_MERGE_COMMIT` **only** after every
mandatory gate passes (see PR checklist). Existing tags `pilot-shared-vps-isolated-deployment-go` and
`pilot-shared-vps-post-go-hardening-go` are **not** moved (UIX-R022).
