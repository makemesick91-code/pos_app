# Aish POS Shared-VPS Pilot — Deployment Evidence

Real command output captured during the deployment on **2026-07-12** (UTC).
Secrets are redacted; no password, `APP_KEY`, or token appears here.

## Environment

| Field | Value |
|---|---|
| VPS hostname | `srv1730088` |
| OS | Ubuntu 24.04.4 LTS (kernel 6.8.0-134) |
| CPU / RAM / Disk | 2 vCPU / 7.8 GiB / 96 GB (5% used) |
| Swap | none (0 B) — watch item |
| POS repo | `github.com/makemesick91-code/pos_app` |
| Branch / commit | `main` / `8b78fbf7678d84e62d4de0bf02ec3a6430689141` |
| PHP (POS) | 8.5.8 (ondrej) · PHP (daeng) 8.3.6 (unchanged) |

## Phase 1 — DaengtisiaMS baseline (before)

```
http://127.0.0.1/ (Host _)  → status=302 (→/login)  time≈0.02–0.53s
services: nginx active, postgresql active, php8.3-fpm active, daeng worker active
daeng git: HEAD 6da05b5, working tree clean
postgres: max_connections=100, daeng=1 conn
```

## Phase 2 — Backups

```
config backups → /var/backups/aish-pos/config/{nginx-sites-available,nginx-sites-enabled,php-fpm-pools,systemd-units-baseline}-20260712_090621
DaengtisiaMS DB safety dump → /var/backups/aish-pos/database/daeng_safety_20260712_090621.dump (1.1M)
  pg_restore --list → OK, 1284 TOC entries
ufw active (OpenSSH/80/443); port 8080 FREE
```

## Phase 3-4 — Clone + dependencies

```
git clone main → /var/www/aish-pos   HEAD=8b78fbf, clean tree
required PHP extensions: all present (pdo_pgsql, pgsql, mbstring, intl, bcmath, gd, zip, curl, xml, ...)
composer validate --strict → valid ; check-platform-reqs → all success (under PHP 8.5)
composer install --no-dev --optimize-autoloader → DONE
npm install + npm run build → built in 352ms (public/build/assets/*, manifest)
```

### PHP-version blocker (resolved)

```
composer install under 8.3 → FAILED:
  laravel/framework v13.18.1 → symfony/http-foundation v8.1.1 requires php >=8.4.1
  (VPS php was 8.3.6)
Resolution: add ondrej/php PPA; apt install explicit php8.5-* only (no apt upgrade)
  php8.5 -v → PHP 8.5.8 ; php8.3-fpm → still 8.3.6, active (DaengtisiaMS untouched)
```

## Phase 5-6 — Isolated DB / role / .env

```
CREATE ROLE aish_pos_user LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE, conn limit 15
CREATE DATABASE aish_pos_pilot OWNER aish_pos_user (UTF8, template0); REVOKE PUBLIC; schema owned by POS
role attrs → rolsuper=f createdb=f createrole=f canlogin=t connlimit=15
POS user → aish_pos_pilot   : POS_CONNECT_OK=aish_pos_pilot
POS user → asia_dental_lab_pilot:
    SELECT public.cache → ERROR: permission denied for table cache
    CREATE TABLE public.* → ERROR: permission denied for schema public
    information_schema.tables visible → 2 (cannot enumerate daeng app tables)
.env → root:www-data 0640 ; APP_KEY generated ; not git-tracked
```

## Phase 7-8 — Target guard + migrate

```
Laravel effective target → database=aish_pos_pilot driver=pgsql  (GUARD OK)
php artisan migrate --force → 95 migrations DONE
migrations table count = 95 ; total public tables = 105
```

## Phase 9-10 — Optimize + permissions

```
optimize:clear / config:cache / route:cache (Routes cached successfully) / view:cache → OK
route:list → 420 routes
storage, bootstrap/cache → www-data:www-data (dirs 775, files 664)
.env → root:www-data 640 ; public → root:root 755 ; no world-writable files
```

## Phase 11-12 — PHP-FPM pool + Nginx :8080

```
/etc/php/8.5/fpm/pool.d/aish-pos.conf ; default 8.5 www.conf → disabled
php-fpm8.5 -t → successful ; socket /run/php/php8.5-fpm-aish-pos.sock (www-data:www-data)
php8.3-fpm → still active, daeng socket OK
/etc/nginx/sites-available/aish-pos (listen 8080) → enabled ; nginx -t → OK ; reloaded
ufw allow 8080/tcp → added
```

## Phase 14-17 — Worker / scheduler / logrotate / backup

```
systemd aish-pos-queue-worker.service → enabled + active (Main PID running, php8.5)
daengtisiams-queue-worker → still active (untouched)
/etc/cron.d/aish-pos (scheduler, root:root 644) ; cron active
  artisan schedule:list → "No scheduled tasks have been defined" (cron is a harmless no-op for now)
logrotate -d /etc/logrotate.d/aish-pos → valid
/usr/local/sbin/backup-aish-pos + /root/.pgpass (0600) ; /etc/cron.d/aish-pos-backup (daily 02:30)
  manual run → Backup verified: aish_pos_20260712_092117.dump (552K), 510 TOC, no daeng content
```

## Phase 20 — Smoke test

```
/health/live   → 200  {"status":"ok",...}   ~0.19s (first) / 0.03s
/health/ready  → 200  {"status":"ok",...}   0.03s
/ (landing)    → 200  8621 bytes
external http://145.79.13.224:8080/health/live → 200  0.028s
QUEUE round-trip: dispatch → jobs=1 → worker consumed → jobs=0, failed_jobs=0, log marker present → OK
SECURITY:  /.env → 403 · /storage/logs/laravel.log → 403 · /index.php/../.env → 403
```

## Phase 21 — DaengtisiaMS regression (after)

```
http://127.0.0.1/ (Host _) → status=302, time 0.016–0.035s   (baseline 302, 0.02–0.53s — no regression)
nginx -t OK · php-fpm8.3 -t OK · php-fpm8.5 -t OK
services active: nginx, postgresql, php8.3-fpm, php8.5-fpm, daengtisiams-queue-worker, aish-pos-queue-worker, cron
daeng git: HEAD 6da05b5 unchanged, 0 dirty  ← DaengtisiaMS untouched
postgres connections: aish_pos_pilot=1, asia_dental_lab_pilot=1, postgres=1  (8/100 total)
RAM after: 786Mi used / 7.0Gi available (baseline 643Mi / 7.1Gi — POS ≈ +140MB)
load 0.00 ; disk 92G free (5% used)
POS: APP_DEBUG=false, APP_ENV=production ; repo tree clean (only untracked package-lock.json)
```

## Gate summary

| Gate | Result |
|---|---|
| POS repo clean | PASS |
| Backup DaengtisiaMS valid | PASS (1284 TOC) |
| Backup POS valid | PASS (510 TOC) |
| Database separate | PASS |
| DB user separate & non-super | PASS |
| Migration success | PASS (95) |
| Nginx valid | PASS |
| PHP-FPM valid (8.3 + 8.5) | PASS |
| Worker POS active | PASS |
| Scheduler POS active | PASS (no tasks defined yet) |
| HTTPS active | N/A — deferred (no domain/TLS) |
| POS smoke test | PASS |
| DaengtisiaMS regression | PASS (untouched) |
| No critical error | PASS |
| Deploy evidence saved | PASS (this file) |
| Rollback runbook | PASS |
| Uninstall runbook | PASS |
