# Backup / Restore Governance

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

Governance and evidence rules for backup and restore. This is a documentation
check only — `production:backup-governance-check` verifies this doc exists and
covers the required sections. It never executes a real backup, never executes a
real restore, and never prints database credentials. Real credentials live only
in the operator secret store.

## Backup ownership

The **Technical owner** owns backups. A named deputy is assigned in the operator
secret store. Ownership is reviewed at every post-handover operations run.

## Backup frequency (placeholder)

- Database: daily automated backup + retained snapshots (frequency confirmed in
  the operator secret store, never here).
- Configuration/secrets: versioned in the secret store, never in this repository.

## Restore rehearsal cadence

A restore rehearsal is performed on a fixed cadence (placeholder — e.g. monthly)
into an isolated environment. Each rehearsal produces evidence: timestamp,
operator, dataset scope, and result. Never rehearse against live production data.

## Rollback relation

Backup/restore is the last line of the release **rollback** plan: if a release or
maintenance window cannot be rolled back by redeploying the prior candidate, the
verified backup is restored. See `release-rollback-governance.md`.

## Backup verification evidence

Each backup must be verified (integrity check / test restore) and the evidence
recorded (timestamp, operator, result reference). A missing or stale verification
lowers the backup-restore readiness signal to WARN/FAIL.

## Evidence placeholders

| Evidence | Where recorded |
| --- | --- |
| Last successful backup | Operator secret store (reference only in ops run) |
| Last verified restore rehearsal | Operator secret store |
| Backup owner / deputy | This doc + secret store |

## Safety rules

- No real DB credentials, connection strings, or server IPs in this repository.
- No real backup or restore executed by any Sprint 19 command.
