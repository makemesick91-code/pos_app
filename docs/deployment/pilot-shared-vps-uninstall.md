# Aish POS Shared-VPS Pilot — Uninstall Runbook

Removes the entire pilot from the VPS, leaving **DaengtisiaMS fully intact**.
Nothing here touches `asia-dental-lab`, `php8.3-fpm`, `dental_pilot`,
`asia_dental_lab_pilot`, or any shared Redis (there is none). Do the destructive
DB/role drops **last**, and only after explicit approval.

## Components owned by the pilot (the complete list)

```
/etc/nginx/sites-enabled/aish-pos
/etc/nginx/sites-available/aish-pos
/etc/php/8.5/fpm/pool.d/aish-pos.conf        (+ www.conf.disabled we renamed)
/etc/systemd/system/aish-pos-queue-worker.service
/etc/cron.d/aish-pos
/etc/cron.d/aish-pos-backup
/etc/logrotate.d/aish-pos
/usr/local/sbin/backup-aish-pos
/root/.pgpass                                (POS line only — see note)
/var/www/aish-pos
/var/backups/aish-pos
/var/log/nginx/aish-pos-*.log
/var/log/php8.5-fpm-aish-pos-*.log
database aish_pos_pilot
role     aish_pos_user
PHP 8.5 packages (ondrej)                    (optional — only if nothing else uses them)
```

## Ordered procedure

```bash
# 1. maintenance + final backup (keep the evidence)
cd /var/www/aish-pos/backend && php8.5 artisan down
/usr/local/sbin/backup-aish-pos

# 2. stop + disable the worker
systemctl disable --now aish-pos-queue-worker
rm -f /etc/systemd/system/aish-pos-queue-worker.service
systemctl daemon-reload

# 3. disable the nginx vhost, then reload (daeng unaffected)
rm -f /etc/nginx/sites-enabled/aish-pos /etc/nginx/sites-available/aish-pos
nginx -t && systemctl reload nginx

# 4. remove cron + logrotate
rm -f /etc/cron.d/aish-pos /etc/cron.d/aish-pos-backup /etc/logrotate.d/aish-pos

# 5. remove the FPM pool, reload php8.5-fpm
rm -f /etc/php/8.5/fpm/pool.d/aish-pos.conf
# if this was the only 8.5 pool you may stop the service instead:
#   systemctl disable --now php8.5-fpm
php-fpm8.5 -t 2>/dev/null && systemctl reload php8.5-fpm 2>/dev/null || true

# 6. close the firewall port (post-hardening the rule is operator-IP scoped)
ufw delete allow from <OPERATOR_IP> to any port 8080 proto tcp
ufw delete allow 8080/tcp 2>/dev/null || true   # in case an older global rule exists

# 7. VERIFY DaengtisiaMS before any destructive DB step
curl -s -o /dev/null -w "daeng=%{http_code}\n" http://127.0.0.1/ -H "Host: _"   # expect 302
nginx -t && php-fpm8.3 -t && systemctl is-active postgresql daengtisiams-queue-worker

# 8. archive app + backups if desired
tar czf /root/aish-pos-archive-$(date +%Y%m%d).tar.gz /var/www/aish-pos /var/backups/aish-pos
rm -rf /var/www/aish-pos /var/backups/aish-pos
rm -f /var/log/nginx/aish-pos-*.log /var/log/php8.5-fpm-aish-pos-*.log

# 9. DROP database + role — ONLY after explicit approval, DB first then role
sudo -u postgres psql -c "DROP DATABASE IF EXISTS aish_pos_pilot;"
sudo -u postgres psql -c "DROP ROLE IF EXISTS aish_pos_user;"

# 10. remove the POS credential line from /root/.pgpass
#     (it only ever contained the POS line; safe to delete the file if unused elsewhere)
rm -f /root/.pgpass

# 11. final DaengtisiaMS re-verify
curl -s -o /dev/null -w "daeng=%{http_code}\n" http://127.0.0.1/ -H "Host: _"
git -C /var/www/asia-dental-lab-v2 status --porcelain | wc -l   # expect 0
```

## Post-GO hardening artifacts (2026-07-12)

These were added by the hardening pass. On a **POS-only uninstall, keep the ones
that protect DaengtisiaMS or the host** — they are not POS-owned:

- **Swap** (`/swapfile`, `/etc/fstab` line, `/etc/sysctl.d/99-aish-pos-swap.conf`)
  — host-level; keep unless the VPS is being repurposed. To remove: see
  [rollback §5.3](pilot-shared-vps-rollback.md).
- **PHP 8.3 apt holds** — **keep**; they protect DaengtisiaMS' runtime, not POS.
- **PostgreSQL `PUBLIC CONNECT` revoke on `asia_dental_lab_pilot`** — **keep**;
  this is a DaengtisiaMS-side hardening (explicit `dental_pilot` CONNECT, no
  PUBLIC). Dropping the POS role/DB does not require reverting it.
- **Scheduled runtime prunes** live in `backend/routes/console.php` and stop
  automatically once `/etc/cron.d/aish-pos` (step 4) and `/var/www/aish-pos`
  (step 8) are removed.

## Notes / cautions

- **PHP 8.5 / ondrej PPA** were added for this pilot. Leave them unless you are
  certain nothing else needs 8.5. If removing: `apt-get purge 'php8.5-*'` then
  optionally `add-apt-repository -r ppa:ondrej/php`. Do **not** `apt upgrade`
  during removal — it could pull the ondrej `php8.3` into DaengtisiaMS.
- **Never** run `FLUSHALL`/`FLUSHDB` or global Redis operations (there is no
  Redis; POS uses the database for cache/queue/session).
- The DaengtisiaMS database **data** was never modified; there is no POS table
  inside it. The only database-level change was hardening its connect ACL
  (revoke `PUBLIC CONNECT`, keep explicit `dental_pilot` CONNECT) — leave that in
  place; there is no POS grant on it to revoke.
- Keep the archive (step 8) until the pilot is confirmed no longer needed.
