# UIX-5 Rollback — Subscription, Billing & Invoice Console

UIX-5 adds no migration and no schema change, so rollback is a code revert only.
Roll back if runtime verification fails, DMS shows any regression, or billing data
is at risk of public plaintext exposure.

## Preconditions
- Previous release commit recorded (from the runbook, step 2).
- DB backup of `aish_pos_pilot` captured before deploy (defensive; unused for a
  no-migration rollback but required by rule 80).

## Procedure (on the VPS, encrypted channel only)
1. `cd /var/www/aish-pos`
2. `git checkout <previous-release-commit>`
3. `composer install --no-dev --optimize-autoloader` (in `backend/`)
4. Rebuild caches, then **restore FPM ownership** (UIX5-R026):
   `chown -R www-data:www-data storage/framework bootstrap/cache`
5. `systemctl reload php8.5-fpm nginx` (Aish resources only — never php8.3/DMS)
6. Runtime re-verify: `/health/live`, `/health/ready`, `/owner/login`,
   `/admin/login`, and confirm the billing routes are gone/return as expected for
   the previous release.
7. DMS non-regression re-check (rule 80): DMS `/`, `/login`, `SELECT 1` healthy.

## Notes
- Never `git push --force`, never move an existing GO tag, never modify DMS.
- Because there is no migration, no `migrate:rollback` is required. If a future
  UIX-5 revision adds a migration it must ship a tested down path first.
