# Aish POS Shared-VPS Pilot — Post-GO Security Hardening

Hardening performed **2026-07-12 (UTC)** on the shared VPS `srv1730088`
(145.79.13.224), on top of the GO'd isolated deployment. This is **not** a
re-deploy: it closes the hardening/operational-readiness items flagged during the
pilot GO review, without disrupting the co-hosted **DaengtisiaMS** app.

Secrets are redacted throughout; no password, `APP_KEY`, `.pgpass`, or token
value appears here.

## Commit terminology (authoritative)

| Meaning | Commit |
|---|---|
| Runtime foundation commit | `8b78fbf` |
| Shared-VPS deployment merge commit / deployed `HEAD` before hardening | `f682ec7` |
| Existing GO tag `pilot-shared-vps-isolated-deployment-go` target (unchanged) | `f682ec7` |
| Hardening branch | `feature/pilot-shared-vps-post-go-hardening` |

## Access model (final)

| Item | State |
|---|---|
| POS domain | **none available** at hardening time |
| HTTPS | **BLOCKED** — no domain/DNS; no certificate issued (not faked) |
| Public unrestricted HTTP on :8080 | **DISABLED** |
| Access mode | HTTP on `:8080`, **UFW-restricted to operator IP `<OPERATOR_IP>`** |
| SSH | unaffected (separate `OpenSSH` UFW rule) |
| APP_URL / SESSION_SECURE_COOKIE | `http://145.79.13.224:8080` / `false` (correct for HTTP transport) |

**Status:** technical restricted-access pilot = **GO**; public HTTPS pilot =
**BLOCKED (no domain)**; public real-data HTTP pilot = **NO-GO** until HTTPS.

### Firewall — before → after

```
BEFORE (global exposure):
[4] 8080/tcp        ALLOW IN  Anywhere
[8] 8080/tcp (v6)   ALLOW IN  Anywhere (v6)

Commands:
sudo ufw --force delete allow 8080/tcp        # removes v4 + v6 global rule
sudo ufw allow from <OPERATOR_IP> to any port 8080 proto tcp

AFTER:
[1] OpenSSH         ALLOW IN  Anywhere
[2] 80/tcp          ALLOW IN  Anywhere            # DaengtisiaMS (unchanged)
[3] 443/tcp         ALLOW IN  Anywhere            # reserved (nothing listens yet)
[4] 8080/tcp        ALLOW IN  <OPERATOR_IP>     # POS, operator-only
Default: deny (incoming)
```

Validation: `curl http://127.0.0.1:8080/health/live` → `200`; no global 8080
ALLOW remains; nginx still listens `0.0.0.0:8080` + `[::]:8080` (IPv6 8080 now
falls under default-deny — no IPv6 allow rule).

### When a domain becomes available (HTTPS path, deferred)

1. Point DNS `A` for `<POS_DOMAIN>` → `145.79.13.224`; verify `dig +short A`.
2. `apt-get install --no-install-recommends certbot python3-certbot-nginx`
   (targeted install, **never** a blanket upgrade — PHP 8.3 is held, see below).
3. Add a name-based `server_name <POS_DOMAIN>` vhost (80→443 redirect + 443 TLS)
   pointing to `unix:/run/php/php8.5-fpm-aish-pos.sock`; `certbot --nginx -d <POS_DOMAIN> --redirect`; `certbot renew --dry-run`.
4. Set `APP_URL=https://<POS_DOMAIN>` + `SESSION_SECURE_COOKIE=true`; rebuild
   caches; reload php8.5-fpm + nginx.
5. Then remove/localhost-bind the `:8080` access. Only then is public HTTPS = GO.

## Resource safety — 2 GiB swap

```
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile
sudo mkswap /swapfile && sudo swapon /swapfile
grep -qE '^/swapfile\s' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' > /etc/sysctl.d/99-aish-pos-swap.conf && sysctl --system
```

Validation: `swapon --show` → `/swapfile 2G`; `free -h` Swap `2.0Gi`;
`vm.swappiness = 10`; persistent via `/etc/fstab`.

## Runtime isolation — DaengtisiaMS PHP 8.3 hold

The Ondřej PPA ships newer 8.3 point-releases; a blanket `apt upgrade` could move
DaengtisiaMS off its validated runtime. The **exact installed** 8.3 package set is
pinned (`apt-mark hold`):

```
php8.3 php8.3-bcmath php8.3-cli php8.3-common php8.3-curl php8.3-fpm
php8.3-gd php8.3-intl php8.3-mbstring php8.3-opcache php8.3-pgsql
php8.3-readline php8.3-xml php8.3-zip
```

Validation: `apt-mark showhold` lists all 14; `php8.3 -v` → 8.3.6; `php8.5 -v` →
8.5.8; `php-fpm8.3 -t` + `php-fpm8.5 -t` both OK. **No blanket apt upgrade was
performed.** Evidence saved to
`/var/backups/aish-pos/post-go-hardening/config/php83-installed-*.txt` and
`apt-holds-after-*.txt`.

### PHP maintenance policy (shared VPS)

1. `apt-get update` → `apt list --upgradable` → `apt-cache policy <pkg>`.
2. Back up; test DaengtisiaMS on 8.3; upgrade only explicitly selected packages.
3. Never blanket-upgrade. `apt-mark unhold` only with a documented reason.

## Database isolation — PostgreSQL PUBLIC CONNECT hardening

The POS role `aish_pos_user` (NOSUPERUSER/NOCREATEDB/NOCREATEROLE, conn limit 15)
could previously **connect** to `asia_dental_lab_pilot` via the default
`PUBLIC CONNECT` (it could not read tables, but connect was open). Hardened by
granting the real DaengtisiaMS runtime role explicit CONNECT **before** revoking
PUBLIC:

```sql
GRANT CONNECT ON DATABASE asia_dental_lab_pilot TO dental_pilot;   -- runtime role (verified via pg_stat_activity + .env)
REVOKE CONNECT ON DATABASE asia_dental_lab_pilot FROM PUBLIC;
```

ACL before → after:

```
before: {=Tc/postgres, postgres=CTc/postgres, dental_pilot=CTc/postgres}   -- PUBLIC has c(onnect)
after : {=T/postgres,  postgres=CTc/postgres, dental_pilot=CTc/postgres}   -- PUBLIC c removed, dental_pilot keeps Cc
```

Validation:
- DaengtisiaMS app: `DB::selectOne('SELECT 1')` → ok; HTTP `/`=302, `/login`=200.
- `aish_pos_user` → `asia_dental_lab_pilot`: **DENIED** (`psql` exit 2, `FATAL`),
  while the same credentials succeed on `aish_pos_pilot` (proving denial is the
  CONNECT revocation, not a bad password).
- `aish_pos_pilot` already had no PUBLIC entry (`{aish_pos_user=CTc}`) — isolated
  at original deploy; left unchanged.

**Emergency rollback:** `GRANT CONNECT ON DATABASE asia_dental_lab_pilot TO PUBLIC;`
(only if the real runtime role turns out to differ and Daeng loses access).

## What was NOT done (by rule)

No DaengtisiaMS `.env` edit, no Daeng migrations, no Daeng vhost edit, no VPS
reboot, no `apt upgrade`/`full-upgrade`, no PHP removal, no `redis FLUSHALL`, no
`migrate:fresh`/`db:wipe`, no GO-tag move.

> **Note on Daeng HEAD:** during this window the operator independently
> fast-forwarded the DaengtisiaMS working branch (`6da05b5` → `7ad7c49`, their own
> PR #256) — this is normal DaengtisiaMS development, unrelated to and unaffected
> by this hardening. Daeng remained healthy across the change.

## Backups taken before hardening

`/var/backups/aish-pos/post-go-hardening/config/` (timestamp `20260712_100400`):
nginx/fpm/worker/cron configs, `ufw-*.txt`, `packages-*.txt`, `apt-holds-*.txt`,
verified Aish POS DB dump, and a verified **Daeng DB pre-hardening dump**
(`daeng-db-pre-pg-hardening-*.dump`, `pg_restore --list` OK).
