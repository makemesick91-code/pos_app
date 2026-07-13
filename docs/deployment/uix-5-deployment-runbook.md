# UIX-5 Deployment Runbook — Subscription, Billing & Invoice Console

UIX-5 is a backend/Blade-only change (no migrations, no queue/scheduler change,
no Android change). It adds read-only billing routes/views to the existing
`/owner/*` and `/admin/*` consoles. Deployment follows the same isolated
shared-VPS procedure as UIX-3/UIX-4.

## Preconditions (all required before deploy)
1. Authoritative `pull_request` CI green on the merge commit (rule 70).
2. Code merged to `main`; final release commit identical on local, `origin/main`,
   and the VPS checkout after pull (rule 90).
3. DB backup of `aish_pos_pilot` captured and current release commit recorded
   (rule 80) — even though UIX-5 adds no migration, back up before any deploy.
4. Encrypted operator channel only (SSH tunnel / VPN); port 8080 stays
   IP-restricted. Public plaintext-HTTP billing exposure is NO-GO (UIX5-R025).

## Isolation invariants (never change)
- php8.5-fpm pool `aish-pos`, nginx site `aish-pos` on port 8080, systemd unit
  `aish-pos-queue-worker`, database `aish_pos_pilot`.
- DaengtisiaMS (php8.3, `daeng` user, DMS nginx/systemd/DB) is untouched.

## Steps
1. **DMS pre-check** (rule 80): capture DMS HEAD/tag, `/` + `/login` reachable,
   `SELECT 1`, php8.3-fpm active, nginx/PostgreSQL healthy. Record output.
2. **Backup**: `pg_dump`/DB backup of `aish_pos_pilot`; record current Aish HEAD.
3. **Fetch & checkout** the release commit into `/var/www/aish-pos`:
   `git fetch --all --tags && git checkout <release-commit>`.
4. **Dependencies**: `composer install --no-dev --optimize-autoloader` in
   `backend/` (no new runtime deps in UIX-5; no PDF library added).
5. **Caches (ownership-safe, UIX5-R026)**: rebuild config/route/view caches, then
   ensure the FPM runtime user owns compiled state:
   `chown -R www-data:www-data storage/framework bootstrap/cache`.
   Verify no root-owned files remain under those paths.
6. **Reload**: `systemctl reload php8.5-fpm` (pool `aish-pos`) and `nginx`. Do NOT
   touch php8.3 / DMS units.
7. **Runtime verification** (over the encrypted channel):
   - `GET /health/live`, `GET /health/ready` → ok.
   - `GET /owner/login`, `GET /admin/login` → 200.
   - Authenticated owner: `/owner/billing`, `/owner/billing/invoices`, an invoice
     detail, and an invoice `/download` (safe headers, HTML document).
   - Authenticated admin: `/admin/billing`, `/admin/billing/invoices`, an invoice
     detail, `/admin/tenants/{id}/billing`.
   - Cross-tenant / cross-surface denial: a foreign invoice id → 404; owner session
     cannot reach `/admin/billing`; admin session cannot reach `/owner/billing`.
8. **DMS post-check** (rule 80): repeat step 1; confirm DMS unchanged.
9. Capture real (non-placeholder) evidence into
   `docs/deployment/uix-5-deployment-evidence.md`.

## Rollback
See `docs/deployment/uix-5-rollback.md`. UIX-5 has no migration, so rollback is a
checkout of the previous release commit + cache rebuild + ownership fix + reload.
