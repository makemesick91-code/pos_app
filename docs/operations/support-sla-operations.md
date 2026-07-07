# Support / SLA Operations

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

Operational support and SLA rules for the post-handover production phase.
Complements the Sprint 18 `docs/handover/support-sla-handover.md`. No real
contact details, phone numbers, or customer data appear here.

## Support hours (placeholder)

Support hours and on-call rotation are defined in the operator secret store
(placeholder — e.g. business hours + on-call for P0/P1). This doc records the
governance contract only.

## P0–P4 response / resolution targets

| Severity | Response target | Resolution target |
| --- | --- | --- |
| P0 | 4h | 4h |
| P1 | 8h | 8h |
| P2 | 24h | 24h |
| P3 | 72h | 72h |
| P4 | 168h | 168h |

These targets mirror `config/production_operations.php` (`incident_sla_hours`) and
the incident response runbook. `production:incident-summary` flags any open
incident past its SLA due time.

## Escalation owner role

| Level | Owner role |
| --- | --- |
| Intake / triage | Support owner |
| Technical escalation | Technical owner |
| Business/GO decision | Operations owner |

## Issue intake (placeholder)

Operators report issues via the support channel (placeholder — recorded in the
secret store). Support triages into the incident register with severity, area,
and impact.

## Support evidence requirements

- Every P0/P1 incident has a recorded response time and resolution time.
- SLA breaches are captured (`sla_breached_at`) and reviewed at each ops run.
- Accepted-risk incidents carry an approver, reason, and expiry.

## Safety rules

- No real alerts sent by operations commands.
- No secrets, credentials, or customer private data in this repository.
