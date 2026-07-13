# UIX-6 Deployment Runbook — Support, Observability & Incident Console

Read-only presentation sprint. No schema migration is introduced by UIX-6 (it
reuses Sprint 35/36 tables). Deployment mirrors the UIX-5 runbook.

## Preconditions
- Authoritative `pull_request` CI (`UIX-6 CI` + all required workflows) green on the
  release commit, merged to `main` (rule 70).
- Rollback point recorded (previous Aish HEAD) and DB backup captured (rule 80).
- Operator on the encrypted channel; HTTPS `aishpos.online` live.

## Isolation invariants (never change)
- php8.5-fpm pool `aish-pos`, nginx site `aish-pos` on port 8080, systemd unit
  `aish-pos-queue-worker`, database `aish_pos_pilot`, app root `/var/www/aish-pos`.
- DaengtisiaMS (`/var/www/asia-dental-lab-v2`, php8.3, `daeng` user) is NEVER touched.
- Public domain `aishpos.online` (Certbot TLS → proxy `:8080`); HTTP→HTTPS 301.
- `chown -R www-data:www-data storage/framework bootstrap/cache` is the confirmed
  ownership fix after any root-run artisan cache command (UIX6-R031).

## Steps
1. **DMS pre-check** (rule 80 / UIX6-R032): capture DaengtisiaMS HEAD/tag, `/` +
   `/login` reachable, `SELECT 1`, php8.3-fpm active, nginx/PostgreSQL healthy.
2. **Backup**: `pg_dump` of `aish_pos_pilot`; record current Aish HEAD as rollback point.
3. **Fetch & checkout** the release commit into `/var/www/aish-pos`:
   `git fetch --all --tags && git checkout <commit>`.
4. **Dependencies**: `composer install --no-dev --optimize-autoloader` in `backend/`.
5. **Migrations**: `php artisan migrate --force` (UIX-6 adds none; confirm
   `migrate:status` is all Ran).
6. **Caches (ownership-safe, UIX6-R031)**: rebuild `config:cache`, `route:cache`,
   `view:cache`, then `chown -R www-data:www-data storage/framework bootstrap/cache`;
   verify 0 root-owned files under `storage/framework`.
7. **Reload**: `systemctl reload php8.5-fpm` (pool `aish-pos`) and `nginx`. Do NOT
   touch php8.3 / DMS units.
8. **Runtime verification** over HTTPS (UIX6-R029/R030):
   - `/health/live`, `/health/ready` → 200; `http://` → 301 `https://`.
   - Unauthenticated `/admin/support`, `/admin/observability`, `/admin/incidents`
     → 302 `/admin/login`; `/owner/support` → 302 `/owner/login`.
   - Authenticated (safe throwaway Platform Admin): support overview, tenant
     health list/detail, observability overview (truthful freshness), incident
     list/detail render 200.
   - Authenticated (safe throwaway Tenant Owner): `/owner/support` renders own
     tenant only; a foreign incident id → 404.
   - Cross-tenant / cross-surface denial confirmed; no raw log/stack-trace/secret
     rendered; no new Laravel log errors.
   - Clean up all synthetic accounts/records; verify pristine state.
9. **DMS post-check** (rule 80): repeat step 1; confirm DMS unchanged.
10. Capture real (non-placeholder) evidence into
    `docs/deployment/uix-6-deployment-evidence.md`.

## Rollback
See `docs/deployment/uix-6-rollback.md`.
