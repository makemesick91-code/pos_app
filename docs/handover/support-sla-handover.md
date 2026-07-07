# Support / SLA Handover

Sprint 18 — Pilot Closure & Production Handover Foundation.

Support model handed to the production support owner. **No real personal phone
numbers, emails, or passwords are required** — placeholders only.

## Support hours

- Support hours: _placeholder_ (e.g. Mon–Sat 08:00–20:00 WIB).
- After-hours: _placeholder_ (on-call rotation reference only).

## Severity SLA

| Severity | Response target | Resolution target |
|----------|-----------------|-------------------|
| BLOCKER | _placeholder_ (e.g. 1h) | _placeholder_ (e.g. 8h) |
| CRITICAL | _placeholder_ (e.g. 2h) | _placeholder_ (e.g. 24h) |
| MAJOR | _placeholder_ (e.g. 8h) | _placeholder_ (e.g. 72h) |
| MINOR | _placeholder_ | _placeholder_ (e.g. 7d) |
| TRIVIAL | _placeholder_ | _placeholder_ (e.g. 14d) |

SLA targets align with `config/pilot_stabilization.php` (`severity_sla_hours`)
and `docs/pilot/field-issue-severity-sla.md`.

## Escalation owner

- Primary escalation: **SUPPORT** role owner (see ownership matrix).
- Technical escalation: **TECHNICAL** role owner.

## Issue intake channel

- Intake channel: _placeholder_ (ticket queue / shared inbox reference).
- Every intake becomes a defect in `/api/v1/admin/pilot-defects` with a severity.

## Response / resolution tracking

- SLA breach detection: `php artisan pilot:sla-check` (read-only).
- Breaches are surfaced in the burn-down and stabilization gates.
