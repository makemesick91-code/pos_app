# UIX-3 — Deployment Evidence

Structured evidence template for the UIX-3 platform-admin Control Center deploy
to the pilot shared VPS (`daengtisiams-vps`). Runtime-result fields are marked
"pending deploy window — to be recorded" and are filled with **real observed
values** during the deploy window.

> GO requires real observed values in every runtime field below. Do not mark GO
> from expected/placeholder values. Public plaintext HTTP admin access stays
> **NO-GO** — the console must be reached only over the encrypted operator
> channel (SSH tunnel / VPN / private network).

## 1. Pre-deploy baseline

| Item | Value |
| --- | --- |
| AISH HEAD before deploy | pending deploy window — to be recorded |
| Target merge commit to deploy | pending deploy window — to be recorded |
| DaengtisiaMS HEAD (baseline, must be unchanged) | pending deploy window — to be recorded |
| Migrations added by UIX-3 | none (confirmed at code level) |

## 2. Backup

| Item | Value |
| --- | --- |
| Pre-deploy DB backup (`/usr/local/sbin/backup-aish-pos`) run | pending deploy window — to be recorded |
| Backup artifact / location reference | pending deploy window — to be recorded |
| Database | `aish_pos_pilot` |

## 3. Deploy commands + output

| Step | Command | Result |
| --- | --- | --- |
| Fast-forward pull | `git -C /var/www/aish-pos pull --ff-only` | pending deploy window — to be recorded |
| config:cache | `php8.5 .../artisan config:cache` | pending deploy window — to be recorded |
| route:cache | `php8.5 .../artisan route:cache` | pending deploy window — to be recorded |
| view:cache | `php8.5 .../artisan view:cache` | pending deploy window — to be recorded |
| Reload FPM | `systemctl reload php8.5-fpm-aish-pos` | pending deploy window — to be recorded |
| Reload nginx | `systemctl reload nginx` | pending deploy window — to be recorded |

## 4. Migration status

| Item | Value |
| --- | --- |
| `php8.5 .../artisan migrate:status` output (expect unchanged; no UIX-3 rows) | pending deploy window — to be recorded |

## 5. Runtime smoke

| Check | Expected | Observed |
| --- | --- | --- |
| `GET /admin/login` | 200 | pending deploy window — to be recorded |
| `GET /admin` (dashboard, authed) | 200, real data or explicit "unavailable" | pending deploy window — to be recorded |
| `GET /admin/tenants` (list, authed) | 200 | pending deploy window — to be recorded |
| `GET /health/live` | healthy status JSON | pending deploy window — to be recorded |
| `GET /health/ready` | ready status JSON | pending deploy window — to be recorded |

## 6. Security smoke

| Check | Expected | Observed |
| --- | --- | --- |
| Guest -> `/admin` redirects to `/admin/login` | redirect (deny by default) | pending deploy window — to be recorded |
| Tenant (non-admin) user denied console session | generic failure, no session | pending deploy window — to be recorded |
| Authenticated response `Cache-Control` | `no-store, no-cache, must-revalidate, private` | pending deploy window — to be recorded |
| Admin reachable only via encrypted channel | public plaintext unreachable | pending deploy window — to be recorded |

## 7. DaengtisiaMS non-regression

| Check | Expected | Observed |
| --- | --- | --- |
| DaengtisiaMS HEAD equals recorded baseline | unchanged | pending deploy window — to be recorded |
| DaengtisiaMS site healthy | healthy | pending deploy window — to be recorded |
| `php8.3-fpm` / `daengtisiams-queue-worker` untouched | untouched | pending deploy window — to be recorded |
| No blanket `apt upgrade` performed | confirmed | pending deploy window — to be recorded |

## 8. Final commit equality (local / origin / VPS)

| Location | Commit | Match |
| --- | --- | --- |
| Local | pending deploy window — to be recorded | — |
| origin | pending deploy window — to be recorded | — |
| VPS (`/var/www/aish-pos`) | pending deploy window — to be recorded | — |
| All three equal | pending deploy window — to be recorded | — |

## 9. GO decision

| Item | Value |
| --- | --- |
| Backend suite (1395 tests / 39330 assertions) green pre-deploy | pending deploy window — to be recorded |
| All runtime smoke passed with real observed values | pending deploy window — to be recorded |
| All security smoke passed | pending deploy window — to be recorded |
| DaengtisiaMS unchanged and healthy | pending deploy window — to be recorded |
| Admin access confined to encrypted operator channel (public plaintext = NO-GO) | pending deploy window — to be recorded |
| Decision (GO / NO-GO) | pending deploy window — to be recorded |
| Decided by / timestamp | pending deploy window — to be recorded |

> Reminder: GO requires real observed values in the runtime, security,
> non-regression, and commit-equality sections above. Exposing the admin surface
> over public plaintext HTTP is NO-GO regardless of the other results.
