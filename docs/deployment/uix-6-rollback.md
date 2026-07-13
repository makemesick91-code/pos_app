# UIX-6 Rollback — Support, Observability & Incident Console

UIX-6 is additive and read-only (no schema migration, no data mutation), so
rollback is a clean checkout of the previous release commit.

## Preconditions recorded before deploy
- Previous Aish HEAD (rollback target): recorded in step 2 of the runbook.
- DB backup of `aish_pos_pilot`: captured in step 2 (not needed for code rollback
  since UIX-6 adds no migration, but retained for safety).

## Rollback steps
1. `cd /var/www/aish-pos && git fetch --all --tags`.
2. `git checkout <previous-release-commit>`.
3. `composer install --no-dev --optimize-autoloader` in `backend/`.
4. Rebuild caches (`config:cache`, `route:cache`, `view:cache`) then
   `chown -R www-data:www-data storage/framework bootstrap/cache` (UIX6-R031).
5. `systemctl reload php8.5-fpm nginx` (Aish pool only; never touch php8.3/DMS).
6. Verify `/health/live` + `/health/ready` → 200, HTTP→HTTPS 301, admin/owner
   login pages reachable.
7. DMS non-regression check: DaengtisiaMS HEAD unchanged, services healthy (rule 80).

## Notes
- No migration to reverse. If the pre-deploy DB backup must be restored for an
  unrelated reason, restore into `aish_pos_pilot` only; never touch the DMS database.
- GO tags are immutable — a rollback does not move or delete any existing tag.
