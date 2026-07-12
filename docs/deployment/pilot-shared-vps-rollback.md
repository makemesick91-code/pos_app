# Aish POS Shared-VPS Pilot — Rollback Runbook

Use this to back the pilot out of service **without touching DaengtisiaMS**.
Rollback ≠ uninstall: this stops/disables POS but keeps files, DB, and role so it
can be brought back. For permanent removal, see the
[uninstall runbook](pilot-shared-vps-uninstall.md).

> Golden rule: every command below targets `aish-pos` / `php8.5` / port 8080 /
> `aish_pos_pilot` only. Never run `supervisorctl restart all`, never touch
> `asia-dental-lab`, `php8.3-fpm`, `dental_pilot`, or `asia_dental_lab_pilot`.

## 1. Application rollback (take POS offline)

```bash
# maintenance mode (POS only)
cd /var/www/aish-pos/backend
php8.5 artisan down --retry=60

# stop the POS worker
systemctl stop aish-pos-queue-worker

# disable the POS nginx vhost
rm -f /etc/nginx/sites-enabled/aish-pos
nginx -t && systemctl reload nginx      # daeng vhost unaffected

# (optional) disable the POS FPM pool
mv /etc/php/8.5/fpm/pool.d/aish-pos.conf /etc/php/8.5/fpm/pool.d/aish-pos.conf.disabled
php-fpm8.5 -t && systemctl reload php8.5-fpm
```

At this point POS no longer serves on `:8080`. DaengtisiaMS on `:80` is untouched.

## 2. Code rollback (bad deploy of a newer commit)

The pilot's runtime foundation is `8b78fbf`; the current known-good deployed
commit is the shared-VPS deployment merge `main@f682ec7` (GO tag
`pilot-shared-vps-isolated-deployment-go`). To return to a known-good commit:

```bash
cd /var/www/aish-pos
git fetch origin
git checkout <GOOD_COMMIT>
cd backend
COMPOSER_ALLOW_SUPERUSER=1 php8.5 $(command -v composer) install --no-dev --optimize-autoloader
npm install && npm run build
php8.5 artisan migrate --force        # only if the good commit expects it
php8.5 artisan optimize:clear && php8.5 artisan config:cache && php8.5 artisan route:cache && php8.5 artisan view:cache
systemctl restart aish-pos-queue-worker
php8.5 artisan up
```

## 3. Database rollback (POS only)

Restore **only** into `aish_pos_pilot`. Never restore a POS dump into a
DaengtisiaMS database.

```bash
LATEST=$(ls -1t /var/backups/aish-pos/database/aish_pos_*.dump | head -1)
pg_restore --list "$LATEST" | head          # verify it is a POS dump

cd /var/www/aish-pos/backend
php8.5 artisan down
# restore as the POS role via .pgpass (clean+restore into the POS db only)
PGPASSFILE=/root/.pgpass pg_restore --clean --if-exists \
  --host=127.0.0.1 --username=aish_pos_user --dbname=aish_pos_pilot "$LATEST"
php8.5 artisan up
```

## 4. Post-rollback verification

```bash
# DaengtisiaMS still healthy
curl -s -o /dev/null -w "daeng=%{http_code}\n" http://127.0.0.1/ -H "Host: _"   # expect 302
nginx -t && php-fpm8.3 -t
systemctl is-active nginx postgresql php8.3-fpm daengtisiams-queue-worker
free -h
# daeng git untouched
git -C /var/www/asia-dental-lab-v2 rev-parse HEAD    # expect 6da05b5..., 0 dirty
```

Record the **cause** of the rollback and the commit/backup restored to.

> Note: the DaengtisiaMS working branch advances under its own development
> (e.g. `6da05b5 → 7ad7c49`). "Untouched by POS work" means POS never *wrote* to
> that repo — not that its HEAD is frozen. Confirm the tree is clean rather than
> asserting a fixed hash.

## 5. Post-GO hardening rollback (2026-07-12 pass)

Config backups referenced below live in
`/var/backups/aish-pos/post-go-hardening/config/` (timestamp `20260712_100400`).
Roll back **only** the item that regressed; each is independent.

### 5.1 Firewall (port 8080)

```bash
# restore the pre-hardening ufw state for reference
sudo cat /var/backups/aish-pos/post-go-hardening/config/ufw-20260712_100400.txt
# remove the operator-IP rule; re-open ONLY if explicitly approved (do NOT
# silently restore unrestricted public HTTP):
sudo ufw delete allow from <OPERATOR_IP> to any port 8080 proto tcp
sudo ufw status numbered
```

### 5.2 HTTPS / nginx vhost (only if a domain vhost was later added)

```bash
sudo cp /var/backups/aish-pos/post-go-hardening/config/aish-pos-nginx-20260712_100400 \
        /etc/nginx/sites-available/aish-pos
sudo nginx -t && sudo systemctl reload nginx
# restore APP_URL / SESSION_SECURE_COOKIE to match the active transport (HTTP → secure=false)
```

### 5.3 Swap

```bash
sudo swapoff /swapfile
sudo rm -f /swapfile
sudo rm -f /etc/sysctl.d/99-aish-pos-swap.conf
# remove ONLY the exact swapfile line from /etc/fstab:
sudo sed -i '\#^/swapfile none swap sw 0 0$#d' /etc/fstab
```

### 5.4 PHP 8.3 package holds

```bash
# document why/when before unholding — these protect DaengtisiaMS' runtime
sudo apt-mark unhold php8.3 php8.3-bcmath php8.3-cli php8.3-common php8.3-curl \
  php8.3-fpm php8.3-gd php8.3-intl php8.3-mbstring php8.3-opcache php8.3-pgsql \
  php8.3-readline php8.3-xml php8.3-zip
apt-mark showhold
```

### 5.5 PostgreSQL PUBLIC CONNECT (emergency only)

```sql
-- ONLY if the real DaengtisiaMS runtime role differs from dental_pilot and Daeng
-- lost DB access. Restores the previous default, then investigate the role.
GRANT CONNECT ON DATABASE asia_dental_lab_pilot TO PUBLIC;
```

### 5.6 Runtime maintenance

Revert the `backend/routes/console.php` schedule block (or the commit) to disable
the scheduled prunes. The prune commands never drop tables or delete active
runtime data — **do not** delete the `jobs`/`sessions`/`cache` tables.
