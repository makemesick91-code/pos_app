# Aish POS Shared-VPS Pilot — Runtime Maintenance

The pilot runs the Laravel **database** drivers for cache, queue, and session.
Those tables (`jobs`, `failed_jobs`, `job_batches`, `sessions`, `cache`,
`cache_locks`) grow over time and need safe, scheduled pruning plus a read-only
status probe. This document describes the maintenance added in the post-GO
hardening pass (branch `feature/pilot-shared-vps-post-go-hardening`).

All commands are **isolated to the POS database** (`aish_pos_pilot`) via the POS
connection; none touch DaengtisiaMS.

## Scheduled tasks (`backend/routes/console.php`)

The scheduler is driven by the existing per-minute cron
(`/etc/cron.d/aish-pos` → `php8.5 artisan schedule:run`, run as `www-data`).

| Time (UTC) | Command | Effect |
|---|---|---|
| 03:10 | `queue:prune-failed --hours=336` | delete failed jobs older than 14 days (framework) |
| 03:20 | `queue:prune-batches --hours=72 --unfinished=168 --cancelled=168` | prune finished batches >72h; keep unfinished/cancelled 7 days (framework) |
| 03:30 | `pilot:prune-sessions --hours=168 --apply` | delete sessions idle beyond max(168h, session lifetime) |
| 03:40 | `pilot:prune-cache --apply` | delete expired database cache rows + expired cache locks |

All use `withoutOverlapping()`. Times are staggered so no two run together.

> `cache:prune-stale-tags` is intentionally **not** scheduled: it is a no-op on
> the database cache driver (it only prunes Redis tag sets). Expired-row
> reclamation is handled by `pilot:prune-cache`.

## Commands

### `pilot:runtime-storage-status` (read-only)

Reports, for the POS database: per-table row counts and sizes
(`pg_total_relation_size`, PostgreSQL only), queue pending/reserved counts and
oldest pending job age, failed-job count, session count + expired estimate, cache
count + expired estimate, and total database size (`pg_database_size`). Emits a
**GO / WATCH / NO-GO** decision and matching exit code.

```bash
php8.5 artisan pilot:runtime-storage-status          # human-readable
php8.5 artisan pilot:runtime-storage-status --json    # machine-readable
php8.5 artisan pilot:runtime-storage-status --strict  # exit non-zero on WATCH too
```

It never persists, never prints secrets, and degrades gracefully: on non-PostgreSQL
drivers (e.g. the sqlite test DB) size probes report `null`/`n/a` instead of erroring,
and missing tables are reported as `missing`.

### Pilot thresholds (initial)

| Signal | WATCH | NO-GO |
|---|---|---|
| Failed jobs | > 10 | > 100 |
| Oldest pending job | > 5 min | > 30 min |
| Sessions / cache | informational (report counts + expired estimate; no baseline gate) | — |
| Database size | reported for trend only; not a fail-on-size gate | — |

### `pilot:prune-sessions` / `pilot:prune-cache`

Both are **dry-run by default** (require `--apply` to delete), process in chunks
by primary key (`--chunk`, default 1000) to avoid long locks, log **counts only**
(never payloads), and return non-zero on error.

- `pilot:prune-sessions --hours=N` — retention clamps **up** to the configured
  session lifetime, so active sessions are never removed. `--hours` < 1 is rejected.
- `pilot:prune-cache` — deletes only rows whose `expiration` is already in the
  past (unexpired/forever entries are untouched), plus expired `cache_locks`.

```bash
php8.5 artisan pilot:prune-sessions            # dry-run, shows candidates
php8.5 artisan pilot:prune-sessions --apply
php8.5 artisan pilot:prune-cache --json
```

## Index & lock safety (reviewed)

The scheduled prune predicates are index-backed on the runtime PostgreSQL DB:
`sessions.last_activity` → `sessions_last_activity_index`; `cache.expiration` →
`cache_expiration_index`; `cache_locks.expiration` → `cache_locks_expiration_index`;
`failed_jobs.failed_at` covered by `failed_jobs_connection_queue_failed_at_index`.
`job_batches` has only its PK but is tiny (batch rows), so a full scan is
negligible — **no new index was added** (avoiding blind index churn). Chunked
deletes keep lock windows short.

## Tests

`backend/tests/Feature/RuntimeMaintenanceTest.php` (7 tests): command registration;
valid JSON + decision; no-secret output; failed-job/stale-queue NO-GO gating +
non-zero exit; session dry-run-then-apply removing only expired; cache/lock
expired-only pruning; invalid-hours rejection. Runs on the sqlite test DB and
exercises the PostgreSQL-size driver guard.

## Rollback

Disable maintenance by reverting the `backend/routes/console.php` schedule block
(or the commit). The prune commands never drop tables or delete active runtime
data, so no data-loss rollback is required. See
[`pilot-shared-vps-rollback.md`](pilot-shared-vps-rollback.md).
