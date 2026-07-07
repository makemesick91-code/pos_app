# Backup & Restore Runbook

Sprint 13 — Production Readiness & Release Hardening Foundation.

> **Safety:** This runbook contains **no real credentials**. All secrets
> (`$DATABASE_URL`, `$STAGING_DATABASE_URL`) are supplied by the operator from
> the environment at execution time. **No destructive restore is ever run
> against production without explicit, written confirmation.** Sprint 13 does
> not automate production restore.

Command/template source of truth: `App\Services\Release\BackupReadinessService::templates()`.
Backup directory: `storage/app/backups`.

## 1. Pre-release backup checklist

- [ ] Confirm `production:readiness-check` is PASS/WATCH.
- [ ] Confirm free disk space for a full database + storage backup.
- [ ] Confirm `storage/app/backups` is writable (`backup.storage_writable` PASS).
- [ ] Record the current app `versionName` / git commit being released.
- [ ] Take the database backup (below) and verify the file is non-empty.
- [ ] Take the storage backup (below).
- [ ] Copy backups to a secure off-host location (operator responsibility).

## 2. Database backup (template)

```bash
# $DATABASE_URL is read from the environment; never hard-code credentials.
pg_dump "$DATABASE_URL" > backups/backup_$(date +%Y%m%d_%H%M%S).sql
```

Verify:

```bash
test -s backups/backup_*.sql   # backup file exists and is non-empty
```

## 3. Storage backup checklist

```bash
tar -czf backups/storage_$(date +%Y%m%d_%H%M%S).tar.gz storage/app
```

- [ ] Verify the archive lists expected files (`tar -tzf ...`).

## 4. Restore rehearsal checklist (staging only)

> Rehearse restores against **staging**, never production.

```bash
# $STAGING_DATABASE_URL is a NON-production database.
psql "$STAGING_DATABASE_URL" < backups/backup_YYYYMMDD_HHMMSS.sql
```

- [ ] Restore completes without errors on staging.
- [ ] `php artisan migrate:status` shows the expected migrations on staging.
- [ ] Smoke-test key business APIs on staging after restore.

## 5. Rollback notes

- [ ] Keep the previous release's backup until the new release is verified healthy.
- [ ] If a release fails, restore the pre-release backup to the affected environment
      **only after** written go-ahead from the release owner.
- [ ] Document the rollback decision and outcome in the sprint/release log.

## 6. What this runbook must never do

- Never store real credentials in git or in this document.
- Never run a destructive restore against production automatically.
- Never upload backups to a third-party service using committed secrets.
