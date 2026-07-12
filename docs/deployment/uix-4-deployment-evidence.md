# UIX-4 — Deployment Evidence & GO Decision

Real, observed evidence for the UIX-4 Tenant Owner Web Console pilot deployment,
captured over the encrypted operator channel (bind port 8080, IP-restricted). No
placeholder or assumed evidence (rule 90).

## Release identity
- Code PR: **#48** — `feat(uix-4): Tenant Owner Web Console foundation`.
- Code merge commit: **a827d40** (`Merge pull request #48`).
- Authoritative CI on PR #48: **322 checks pass, 0 failures** (backend suite +
  UIX-4/UIX-3/foundation gates + Android across the workflow set).
- Final release commit = this evidence-closure merge; local = origin = VPS
  verified exact-match at GO (see Final synchronization).

## Pre-deploy
- AISH HEAD before deploy (rollback point): `99c73c8`.
- DaengtisiaMS baseline HEAD: `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4`.
- DB backup of `aish_pos_pilot`: verified dump
  `/var/backups/aish-pos/database/aish_pos_20260712_220922.dump` (556K).

## Deploy (UIX-4 adds no migrations / no schema change)
- `git pull --ff-only origin main` → AISH HEAD `a827d40`.
- `migrate:status`: all migrations `Ran`; no new UIX-4 rows (no schema change).
- `config:cache` / `route:cache` / `view:cache`: rebuilt successfully.
- Fixed compiled-cache ownership (`chown -R www-data:www-data storage/framework
  bootstrap/cache`) after root-run cache build, so the `www-data` FPM pool can
  serve compiled views. Aish-only; DMS untouched.
- `systemctl reload php8.5-fpm` + `systemctl reload nginx` (DMS runs on php8.3 —
  not reloaded, not touched).

## Runtime verification (observed HTTP over the channel)
- `GET /health/live` → 200; `GET /health/ready` → 200.
- `GET /owner/login` → 200 (title "Masuk Pemilik Bisnis", `_token` CSRF field,
  `noindex` all present).
- `GET /admin/login` → 200 (prior surface — no regression).
- Owner provisioned via secure `tenant:owner-provision --stdin-password` on an
  isolated throwaway tenant ("Password set."; no plaintext echoed).
- Authenticated flow (cookie jar + CSRF): login followed → 200; `GET /owner`,
  `/owner/outlets`, `/owner/devices`, `/owner/subscription`, `/owner/usage`,
  `/owner/operations` → all 200; dashboard renders the tenant name.
- Security: guest `GET /owner` → 302 (deny-by-default); authenticated
  `Cache-Control: must-revalidate, no-cache, no-store, private`; owner session
  `GET /admin` → 302 (surface separation); `POST /owner/logout` → 302;
  post-logout `GET /owner` → 302 (session invalidated).
- Throwaway tenant + owner deleted after verification (no residual test data).

## DaengtisiaMS non-regression
- DMS HEAD after deploy: `8b0bb6af0a11624d34887e5b70e3a0c7627e34b4` — equals
  baseline (unchanged); worktree clean.
- DMS HTTP: `GET /` → 302, `GET /login` → 200; DMS DB `SELECT 1` → 1.
- `php8.3-fpm` active, `daengtisiams-queue-worker` active; PHP 8.3.6 intact.
- `php8.5-fpm`, `aish-pos-queue-worker`, `nginx` active; PHP 8.5.8 intact. No
  failed systemd units.

## Transport / exposure
- No HTTPS/domain provisioned. The console is bound to port 8080, IP-restricted,
  reached only via the encrypted operator channel. **Public plaintext HTTP use
  with real tenant data was NOT performed and remains NO-GO** (UIX4-R019).

## Final synchronization
- Local `main` = `origin/main` = AISH VPS HEAD at the final release commit
  (verified before tagging).

## GO decision
- Decision: **GO**. All preconditions met — authoritative CI green and merged,
  successful isolated deploy, runtime verification passed with real observed
  values, DaengtisiaMS unaffected and healthy, evidence real (no placeholder),
  and access confined to the encrypted channel.
- Recorded by: release manager (Raushan Fikri Ridha), 2026-07-12 deploy window.
- GO tag: `uix-4-tenant-owner-web-console-go` — annotated, on the final release
  commit; all existing GO tags left immutable.
