# Backup / Restore Handover

Sprint 18 — Pilot Closure & Production Handover Foundation.

Extends `docs/release/backup-restore-runbook.md` for production handover. **No
real credentials, hostnames, or connection strings appear here** — placeholders
only.

## Backup responsibility

- Owner role: **TECHNICAL** (backup role: ADMIN — see ownership matrix).
- Daily automated database backup + retention policy (placeholder schedule).

## Backup command template

```bash
# Placeholder — real host/credentials come from the deploy environment, never git.
mysqldump --single-transaction --host="$DB_HOST" --user="$DB_USER" "$DB_NAME" \
  > "backup-$(date +%F).sql"
```

`$DB_HOST` / `$DB_USER` / `$DB_NAME` are environment variables — never committed.

## Restore rehearsal reference

- Rehearse restore into a scratch database before go-live. See
  `docs/release/backup-restore-runbook.md` and
  `docs/pilot/backend-deployment-dry-run.md`.

## Storage responsibility

- Off-host, access-controlled backup storage (placeholder bucket/path).
- Encrypted at rest; retention per policy.

## Rollback reference

- `docs/pilot/pilot-rollback-checklist.md` for the pilot rollback procedure.

## Rules

- No real passwords, tokens, server IPs, or `.env` values in this document.
