# UIX-3 — Rollback

UIX-3 adds **no migrations** and no schema change, so rollback is a code-only
revert: fast-forward the AISH checkout back to the pre-deploy commit and reload
the FPM pool and nginx. A database restore is only needed in the unlikely event
of unrelated data corruption during the window — the UIX-3 change itself never
mutates tenant data.

## When to roll back

- Post-deploy smoke fails (login page not 200, dashboard/tenant pages error).
- A security smoke check fails (guest not redirected, non-admin gains a session,
  missing `no-store` headers).
- Any sign DaengtisiaMS was affected (must never happen; isolation breach).

## Rollback steps (code-only, primary path)

1. Confirm the previously recorded pre-deploy AISH HEAD (captured in the
   deployment evidence). Call it `<PRIOR_HEAD>`.
2. Fast-forward / reset the AISH checkout back to `<PRIOR_HEAD>`:
   ```bash
   git -C /var/www/aish-pos fetch --all
   git -C /var/www/aish-pos reset --hard <PRIOR_HEAD>
   ```
3. Rebuild caches as the runtime user:
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan config:cache
   php8.5 /var/www/aish-pos/backend/artisan route:cache
   php8.5 /var/www/aish-pos/backend/artisan view:cache
   ```
4. Reload services (no restart needed):
   ```bash
   systemctl reload php8.5-fpm-aish-pos
   systemctl reload nginx
   ```
5. Confirm migration status is unchanged (no UIX-3 migration to unwind):
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan migrate:status
   ```

## Database restore (only if needed)

Only if unrelated corruption is confirmed. Restore from the pre-deploy backup
taken by `/usr/local/sbin/backup-aish-pos` for database `aish_pos_pilot`. Because
UIX-3 is read-only and migration-free, a code rollback alone is the expected and
sufficient action; DB restore is an exceptional fallback, not part of the normal
rollback.

## Verification after rollback

1. `git -C /var/www/aish-pos rev-parse HEAD` equals `<PRIOR_HEAD>`.
2. Runtime smoke:
   ```bash
   curl -s -o /dev/null -w '%{http_code}\n' http://127.0.0.1:8080/admin/login   # 200
   curl -s http://127.0.0.1:8080/health/live
   curl -s http://127.0.0.1:8080/health/ready
   ```
3. The prior application behaviour is restored (whatever surfaces existed before
   UIX-3 respond as before).

## DaengtisiaMS untouched

Rollback touches only `/var/www/aish-pos`, the `aish-pos` FPM pool, and nginx.
DaengtisiaMS (`/var/www/asia-dental-lab-v2`, `php8.3-fpm`,
`daengtisiams-queue-worker`) is never modified during rollback. Confirm its HEAD
still equals its recorded baseline and it remains healthy. Never run a blanket
`apt upgrade` as part of rollback.
