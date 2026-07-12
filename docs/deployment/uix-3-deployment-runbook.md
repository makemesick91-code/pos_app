# UIX-3 — Deployment Runbook (Shared VPS, Isolated)

Deploys the UIX-3 platform-admin Control Center to the pilot shared VPS. UIX-3
adds **no migrations** and no schema change.

## Target environment

- Host: ssh alias `daengtisiams-vps` (srv1730088). Connect over the encrypted
  channel only; do not paste key material.
- AISH app path: `/var/www/aish-pos` (Laravel app lives in `backend/`).
- Services (all AISH-scoped, isolated from the co-hosted DaengtisiaMS app):
  - PHP-FPM pool `aish-pos` on `php8.5-fpm` — reload:
    `systemctl reload php8.5-fpm-aish-pos`
  - nginx site `aish-pos` on port **8080** (IP-restricted) — reload:
    `systemctl reload nginx`
  - systemd worker `aish-pos-queue-worker`
  - Database `aish_pos_pilot`

## Isolation rules (MUST hold)

- DaengtisiaMS (`/var/www/asia-dental-lab-v2`, `php8.3-fpm`,
  `daengtisiams-queue-worker`) MUST stay at its baseline commit, healthy, and
  unchanged. Never touch its files, pool, worker, or DB.
- **Never** run a blanket `apt upgrade`. PHP 8.3 must remain intact for
  DaengtisiaMS; AISH runs on the separate PHP 8.5 stack.
- All AISH artisan commands run against the PHP 8.5 binary as the runtime user.

## Admin network safety

- The `/admin/*` console must be reachable ONLY via an encrypted operator
  channel (SSH tunnel / VPN / private network) while no HTTPS/domain exists.
- nginx site is IP-restricted on port 8080; the admin surface is `noindex`.
- **Public plaintext HTTP admin access = NO-GO.** Do not expose the console
  login or pages over public plaintext HTTP.

## Pre-deploy checks

1. Confirm working tree / target is clean and identify the merge commit to deploy.
2. Record current AISH HEAD (for rollback):
   ```bash
   git -C /var/www/aish-pos rev-parse HEAD
   ```
3. Record DaengtisiaMS baseline HEAD (must be unchanged after deploy):
   ```bash
   git -C /var/www/asia-dental-lab-v2 rev-parse HEAD
   ```
4. Pre-deploy DB backup (records a restore point before any change):
   ```bash
   /usr/local/sbin/backup-aish-pos
   ```

## Deploy sequence

1. **Backup + record HEAD** (see pre-deploy steps 2 and 4 above).
2. **Fast-forward pull** to the merge commit (no rebase, no force):
   ```bash
   git -C /var/www/aish-pos pull --ff-only
   ```
3. **Confirm no migrations are pending** (UIX-3 adds none):
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan migrate:status
   ```
   Expect the status to be unchanged from before the pull (no new UIX-3 rows).
4. **Rebuild caches** as the runtime user:
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan config:cache
   php8.5 /var/www/aish-pos/backend/artisan route:cache
   php8.5 /var/www/aish-pos/backend/artisan view:cache
   ```
5. **Reload services** (reload, not restart — no downtime, worker/pool preserved):
   ```bash
   systemctl reload php8.5-fpm-aish-pos
   systemctl reload nginx
   ```

## Post-deploy runtime smoke (over the encrypted channel)

1. Admin login page reachable:
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/admin/login
   # expect 200
   ```
2. Liveness / readiness:
   ```bash
   curl -s http://127.0.0.1:8080/health/live
   curl -s http://127.0.0.1:8080/health/ready
   ```
3. Authenticated smoke (with a provisioned admin session, over the channel):
   dashboard (`/admin`) and tenant list (`/admin/tenants`) render 200 and show
   real data or explicit "unavailable" states (never fabricated zeros).

## Security smoke

- Guest hitting `/admin` or `/admin/tenants` is redirected to `/admin/login`
  (deny by default).
- A valid tenant (non-admin) user is denied a console session (generic message).
- Authenticated console responses carry `Cache-Control: no-store, ... private`.

## Admin provisioning (over the channel, no visible password arg)

```bash
# interactive (hidden prompt):
php8.5 /var/www/aish-pos/backend/artisan platform:admin-provision --email=ops@example.com --name="Ops"

# non-interactive (one STDIN line; never a visible CLI arg):
printf '%s' "$ADMIN_PW" | php8.5 /var/www/aish-pos/backend/artisan platform:admin-provision --email=ops@example.com --stdin-password
```

Password strength is enforced (>=12 chars, letter+digit, not common, not
containing the account name). There is no seeded default admin.

## DaengtisiaMS non-regression (after deploy)

- `git -C /var/www/asia-dental-lab-v2 rev-parse HEAD` equals the recorded
  baseline (unchanged).
- DaengtisiaMS site still healthy; `php8.3-fpm` and `daengtisiams-queue-worker`
  untouched.

## GO gate

GO requires: caches rebuilt, services reloaded, all runtime + security smoke
checks passing with real observed values, DaengtisiaMS unchanged and healthy,
and admin access confined to the encrypted operator channel. Public plaintext
HTTP admin remains NO-GO. Record evidence in
`docs/deployment/uix-3-deployment-evidence.md`.
