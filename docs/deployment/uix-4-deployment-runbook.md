# UIX-4 — Deployment Runbook (Shared VPS, Isolated)

Deploys the UIX-4 Tenant Owner Web Console to the pilot shared VPS. UIX-4 adds
**no migrations** and no schema change (a new `owner` guard, middleware,
controllers, Blade views, a provisioning command, and audit constants only).

## Target environment
- Host: ssh alias `daengtisiams-vps` (srv1730088). Encrypted channel only; never
  paste key material.
- AISH app path: `/var/www/aish-pos` (Laravel app in `backend/`).
- AISH-scoped services (isolated from the co-hosted DaengtisiaMS):
  - PHP-FPM pool `aish-pos` on `php8.5-fpm` — reload: `systemctl reload php8.5-fpm-aish-pos`
  - nginx site `aish-pos` on port **8080** (IP-restricted) — reload: `systemctl reload nginx`
  - systemd worker `aish-pos-queue-worker`
  - Database `aish_pos_pilot`

## Isolation rules (MUST hold)
- DaengtisiaMS (`/var/www/asia-dental-lab-v2`, `php8.3-fpm`,
  `daengtisiams-queue-worker`) MUST stay at its baseline commit, healthy, and
  unchanged. Never touch its files, pool, worker, or DB.
- **Never** run a blanket `apt upgrade`. PHP 8.3 must remain intact for DMS; AISH
  runs on the separate PHP 8.5 stack.

## Owner console network safety
- `/owner/*` (like `/admin/*`) must be reachable ONLY via an encrypted
  operator/user channel (SSH tunnel / VPN / private network) while no
  HTTPS/domain exists.
- nginx site is IP-restricted on port 8080; owner pages are `noindex`.
- **Public plaintext HTTP access with real tenant data = NO-GO** (UIX4-R019).

## Pre-deploy checks
1. Identify the merge commit to deploy; confirm target working tree is clean.
2. Record current AISH HEAD (for rollback): `git -C /var/www/aish-pos rev-parse HEAD`
3. Record DMS baseline HEAD: `git -C /var/www/asia-dental-lab-v2 rev-parse HEAD`
4. Pre-deploy DB backup: `/usr/local/sbin/backup-aish-pos`

## Deploy sequence
1. Backup + record HEAD (steps 2 and 4 above).
2. Fast-forward pull (no rebase, no force): `git -C /var/www/aish-pos pull --ff-only`
3. Confirm no migrations pending (UIX-4 adds none):
   `php8.5 /var/www/aish-pos/backend/artisan migrate:status`
4. Rebuild caches:
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan config:cache
   php8.5 /var/www/aish-pos/backend/artisan route:cache
   php8.5 /var/www/aish-pos/backend/artisan view:cache
   ```
5. Reload services (no downtime):
   ```bash
   systemctl reload php8.5-fpm-aish-pos
   systemctl reload nginx
   ```

## Post-deploy runtime smoke (over the encrypted channel)
1. Owner login page: `curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/owner/login` → expect 200.
2. Liveness/readiness: `curl -s http://127.0.0.1:8080/health/live` ; `.../health/ready`.
3. Admin login still 200 (`/admin/login`) — no regression to the prior surface.
4. Authenticated owner smoke (with a provisioned owner session, over the channel):
   dashboard (`/owner`), outlets, devices, subscription, usage, operations render
   200 with real data or explicit "Tidak tersedia" states; logout returns to login.

## Security smoke
- Guest hitting `/owner` is redirected to `/owner/login` (deny-by-default).
- A platform-admin session cannot reach `/owner`; an owner session cannot reach `/admin`.
- A foreign outlet/device id returns 404.
- Authenticated owner responses carry `Cache-Control: no-store, ... private`.

## Owner provisioning (over the channel, no visible password arg)
```bash
# interactive (hidden prompt):
php8.5 /var/www/aish-pos/backend/artisan tenant:owner-provision --tenant=<CODE> --email=owner@example.com --name="Owner"

# non-interactive (one STDIN line; never a visible CLI arg):
printf '%s' "$OWNER_PW" | php8.5 /var/www/aish-pos/backend/artisan tenant:owner-provision --tenant=<CODE> --email=owner@example.com --stdin-password
```
Password strength enforced (>=12 chars, letter+digit, not common, not containing
the account name). There is no seeded default owner.

## DaengtisiaMS non-regression (after deploy)
- `git -C /var/www/asia-dental-lab-v2 rev-parse HEAD` equals the recorded baseline.
- DMS site healthy; `php8.3-fpm` and `daengtisiams-queue-worker` untouched.

## GO gate
GO requires: caches rebuilt, services reloaded, all runtime + security smoke
passing with real observed values, DMS unchanged and healthy, and owner access
confined to the encrypted channel. Public plaintext HTTP with real tenant data
remains NO-GO. Record evidence in `docs/deployment/uix-4-deployment-evidence.md`.
