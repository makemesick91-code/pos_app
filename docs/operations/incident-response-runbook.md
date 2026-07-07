# Incident Response Runbook

Sprint 19 — Production Operations Baseline & Post-Handover Governance Foundation.

Governs the lifecycle of a production incident recorded in the
`production_incidents` register (admin API `/api/v1/admin/production-incidents`).
No real phone numbers, passwords, or customer private data are required here; the
service sanitises secret-like values before persistence.

## Incident intake

1. Detect (monitoring signal, operator report, hypercare triage).
2. Record via the admin incident API with severity, area, impact, and title.
3. The service computes the SLA due timestamp from the severity.
4. Assign an owner; transition status as the incident progresses.

## Severity (P0–P4)

| Severity | Meaning | Response target | Resolution target |
| --- | --- | --- | --- |
| P0 | Full outage / data loss risk / all tenants blocked | 4h | 4h |
| P1 | Major function down for many tenants | 8h | 8h |
| P2 | Degraded / workaround exists | 24h | 24h |
| P3 | Minor issue, limited impact | 72h | 72h |
| P4 | Cosmetic / low priority | 168h | 168h |

SLA hours are defined in `config/production_operations.php` (`incident_sla_hours`).

## Impact definition

Impact classifies who/what is affected: single tenant, multiple tenants, single
store, platform-wide, or a specific area (backend API, Android app, auth, tenant
context, product sync, cashier, QRIS payment, offline sync, receipt printer,
inventory, reporting, closing, subscription/device, admin/onboarding, database,
backup/restore, deployment).

## Owner assignment

Every incident must have an assignee before it leaves OPEN. Reassign on handoff.

## Response & resolution targets

Response target = time to acknowledge/assign. Resolution target = time to reach
MITIGATED/RESOLVED. Both derive from severity (table above). An open incident past
its SLA due time is flagged `sla_breached_at` by `production:incident-summary`.

## Lifecycle status

`OPEN → ACKNOWLEDGED → INVESTIGATING → MITIGATED → RESOLVED → CLOSED`, or
`ACCEPTED_RISK` via the dedicated accept-risk endpoint.

## Accepted risk handling

- Open P0/P1 without a valid accepted risk force **NO-GO**.
- Open P2 forces **WATCH** unless mitigation is documented.
- Accepting risk for P0/P1/P2 requires an **approver**, a **reason**, and an
  **expiry/review date**. The original severity is always preserved.
- An expired blocking accepted risk re-forces NO-GO.

## Communication (placeholder)

Communication templates and channels are maintained in the operator secret store.
Operations commands never send real alerts.

## Safety rules

- No secret, password, gateway credential, or customer private data in the
  incident description or metadata.
- No real alert sending; no deployment; no backup/restore execution.
