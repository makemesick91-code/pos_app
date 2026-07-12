# UIX-4 — Rollback

UIX-4 adds no migrations and no schema change, so rollback is a code revert plus
a cache rebuild. No data migration to reverse.

## Preconditions (recorded at deploy time)
- Previous AISH release commit (pre-UIX-4 HEAD).
- Pre-deploy DB backup of `aish_pos_pilot` (`/usr/local/sbin/backup-aish-pos`).
- DaengtisiaMS baseline HEAD (must be unchanged throughout).

## Rollback sequence
1. Return the checkout to the previous release commit (fast-forward/checkout, no
   force-push to any shared branch):
   ```bash
   git -C /var/www/aish-pos checkout <PREVIOUS_RELEASE_COMMIT>
   ```
2. Rebuild caches:
   ```bash
   php8.5 /var/www/aish-pos/backend/artisan config:cache
   php8.5 /var/www/aish-pos/backend/artisan route:cache
   php8.5 /var/www/aish-pos/backend/artisan view:cache
   ```
3. Reload services:
   ```bash
   systemctl reload php8.5-fpm-aish-pos
   systemctl reload nginx
   ```
4. Verify: `/owner/login` no longer served (404/redirect per prior state),
   `/admin/login` and `/health/*` still 200, DMS unchanged and healthy.

## Notes
- The new `owner` auth guard and `tenant.owner.web` alias are additive config;
  reverting the code removes them cleanly. No user data is created or altered by
  the console itself (read-only), so no data cleanup is required beyond removing
  any test owner accounts provisioned during verification.
- If a test owner was provisioned for runtime verification, deactivate or remove
  that account after verification (`is_active = false` immediately revokes access).
