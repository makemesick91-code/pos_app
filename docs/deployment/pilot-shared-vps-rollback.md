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

The pilot was deployed at `main@8b78fbf`. To return to a known-good commit:

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
