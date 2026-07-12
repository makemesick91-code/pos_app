# Aish POS — Shared-VPS Pilot Deployment

Aish POS runs as an **isolated pilot** on the same VPS as **DaengtisiaMS**
(`asia-dental-lab-v2`), sharing only the PostgreSQL server, Nginx, and the OS —
with a separate database, database role, application directory, PHP version/FPM
pool, queue worker, scheduler, logs, and backups. Nothing belonging to
DaengtisiaMS is modified, and the entire pilot can be removed without touching it
(see [uninstall runbook](pilot-shared-vps-uninstall.md)).

## Access

| | |
|---|---|
| URL | `http://145.79.13.224:8080` |
| Health (liveness) | `http://145.79.13.224:8080/health/live` |
| Health (readiness) | `http://145.79.13.224:8080/health/ready` |

The pilot is served on a **dedicated port (8080)** because DaengtisiaMS owns the
port-80 catch-all vhost (`server_name _`) and is reached by raw IP over HTTP.
There is no DNS name and no origin TLS on this VPS (no certbot installed), so the
pilot runs over HTTP and **HTTPS is deferred** until a domain/DNS is provided.
`SESSION_SECURE_COOKIE` is therefore `false`.

## Architecture / isolation matrix

| Concern | DaengtisiaMS (existing, untouched) | Aish POS (pilot) |
|---|---|---|
| App path | `/var/www/asia-dental-lab-v2` | `/var/www/aish-pos` (Laravel in `backend/`) |
| Web root | `.../public` | `/var/www/aish-pos/backend/public` |
| PHP | 8.3.6 (Ubuntu) | **8.5.8** (ondrej PPA) |
| PHP-FPM pool | `www` → `/run/php/php8.3-fpm.sock` | `aish-pos` → `/run/php/php8.5-fpm-aish-pos.sock` |
| Nginx site | `asia-dental-lab` (`:80`, `server_name _`) | `aish-pos` (`:8080`) |
| Database | `asia_dental_lab_pilot` | `aish_pos_pilot` |
| DB role | `dental_pilot` | `aish_pos_user` (NOSUPERUSER, conn limit 15) |
| Queue | systemd `daengtisiams-queue-worker` (DB queue) | systemd `aish-pos-queue-worker` (DB queue) |
| Scheduler | (its own) | `/etc/cron.d/aish-pos` |
| Cache/Session | file / database | **database / database** |
| Logs | its own | `/var/log/nginx/aish-pos-*.log`, `/var/log/php8.5-fpm-aish-pos-*.log`, `backend/storage/logs/*` |
| Backups | `daengtisiams-pilot-snapshot.*` | `/usr/local/sbin/backup-aish-pos` → `/var/backups/aish-pos/database` |

## Runtime model — why database drivers + systemd (not Redis/Supervisor)

The original deployment brief assumed a shared Redis and Supervisor. **Neither is
installed on this VPS**, and DaengtisiaMS does not use them (it uses the database
queue, database session, file cache, and a systemd-managed worker). The Aish POS
backend also defaults to database queue/cache/session and has **no Redis
dependency** (no `predis`, no Horizon). We therefore:

- use **database** for queue, cache, and session — fully self-contained inside
  `aish_pos_pilot`, no new shared service, no Redis DB-index/prefix needed;
- run the worker as a dedicated **systemd unit** `aish-pos-queue-worker.service`,
  mirroring DaengtisiaMS's own pattern.

This is lower-footprint, more isolated, and fully removable.

## PHP 8.5

The app's locked dependencies (`laravel/framework v13.18.1` → `symfony/* v8.1`)
require **PHP ≥ 8.4.1**; the VPS ships 8.3.6 (used by DaengtisiaMS). We installed
**PHP 8.5** from the trusted `ondrej/php` PPA as an **additive** version:
DaengtisiaMS's `php8.3`/`php8.3-fpm` remain at 8.3.6 and untouched. Only explicit
`php8.5-*` packages were installed; **no `apt upgrade` was run**.

> ⚠️ Caution: adding the ondrej PPA makes a newer `php8.3` (8.3.32) available as a
> candidate. A future blanket `apt upgrade` could pull it into DaengtisiaMS. Keep
> upgrades explicit/targeted, or pin, per site policy.

## Environment (`/var/www/aish-pos/backend/.env`, secrets redacted)

```env
APP_NAME="Aish POS"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://145.79.13.224:8080
APP_KEY=<REDACTED>
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=aish_pos_pilot
DB_USERNAME=aish_pos_user
DB_PASSWORD=<REDACTED>

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database
SESSION_COOKIE=aish_pos_pilot_session
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax
SESSION_HTTP_ONLY=true
```

`.env` is `root:www-data 0640` (readable by the FPM pool, not world-readable) and
is **not** tracked by git.

## Database isolation

`aish_pos_user` is `LOGIN`, `NOSUPERUSER`, `NOCREATEDB`, `NOCREATEROLE`,
connection limit 15, and owns `aish_pos_pilot` and its `public` schema. Verified:

- POS user → `aish_pos_pilot`: connects OK.
- POS user → `asia_dental_lab_pilot`: can open a connection (DaengtisiaMS's DB
  still grants `CONNECT` to `PUBLIC` — a DaengtisiaMS-side default we must not
  change), but **cannot read, write, create, or enumerate** any DaengtisiaMS
  data (`SELECT` → *permission denied for table*, `CREATE` → *permission denied
  for schema public*).

> Recommendation for the DaengtisiaMS owner (optional hardening, on their side):
> `REVOKE CONNECT ON DATABASE asia_dental_lab_pilot FROM PUBLIC;`
> then `GRANT CONNECT ... TO dental_pilot;` to fully close cross-connect.

## Operations quick reference

```bash
# health
curl http://145.79.13.224:8080/health/live
curl http://145.79.13.224:8080/health/ready

# worker
systemctl status aish-pos-queue-worker
journalctl -u aish-pos-queue-worker -n 100

# app maintenance (POS only)
cd /var/www/aish-pos/backend
php8.5 artisan down --retry=60
php8.5 artisan up

# backup (manual)
/usr/local/sbin/backup-aish-pos
```

See also: [evidence](pilot-shared-vps-evidence.md) ·
[rollback](pilot-shared-vps-rollback.md) ·
[uninstall](pilot-shared-vps-uninstall.md).
