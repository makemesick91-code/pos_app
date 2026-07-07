# SLA Breach Detection (Sprint 17)

Detected by `php artisan pilot:sla-check [--json] [--strict] [--mark-breached]` and
`SlaBreachDetectionService`.

## Severity SLA table

Configured in `config/pilot_stabilization.php` (`severity_sla_hours`):

| Severity  | Initial SLA |
|-----------|-------------|
| BLOCKER   | 8 hours     |
| CRITICAL  | 24 hours    |
| MAJOR     | 72 hours    |
| MINOR     | 168 hours   |
| TRIVIAL   | 336 hours   |

`sla_due_at = created_at + severity_sla_hours[severity]` (stamped at creation and
recomputed on a severity change).

## Breach detection logic

A defect is breached when it is still **open** (`OPEN`, `IN_PROGRESS`, `FIXED`,
`RETEST`) and `sla_due_at <= now`. Verified/closed/accepted-risk defects are not
counted as open breaches.

## Breach action

- **Read-only (default, used by CI):** `pilot:sla-check` reports the breach count and
  breakdown by severity without mutating any row.
- **Mark (`--mark-breached`):** stamps `sla_breached_at` and appends an `SLA_BREACHED`
  event for each overdue open defect not already flagged. This is an explicit,
  audit-tracked mutation — CI never runs it.

## No real alert sending in Sprint 17

Sprint 17 does **not** send Slack/WhatsApp/email or any real alert. Breach detection
is governance/reporting only.

## Escalation placeholder

When an escalation channel is later wired (future sprint), a breached BLOCKER/CRITICAL
should notify the pilot owner and force a stabilization NO-GO review. For now the
breach surfaces in `pilot:burndown-summary` and `pilot:stabilization-go-no-go`.
