# Pilot Defect Register Runbook (Sprint 17)

The pilot defect register (`pilot_defects` + `pilot_defect_events`) is the single
source of truth for stabilization. It is platform-admin only (`/api/v1/admin/pilot-defects`,
`platform.admin` middleware). No public defect portal exists; no Android defect UI exists.

## Defect intake

1. A pilot issue is triaged from the hypercare workflow, operator feedback, or a
   monitoring signal.
2. Create a defect via `POST /api/v1/admin/pilot-defects` (or `PilotDefectService::create`).
3. Required: `title`, `area`, `severity`. Optional: `tenant_id`, `store_id`,
   `assigned_to`, reproduction fields, `evidence_reference`.

## Severity assignment

| Severity  | Meaning                                             | SLA (initial) |
|-----------|-----------------------------------------------------|---------------|
| BLOCKER   | Pilot cannot continue; same-day action required.    | 8h            |
| CRITICAL  | Core flow broken; urgent action required.           | 24h           |
| MAJOR     | Workaround exists but impacts pilot (WATCH).        | 72h           |
| MINOR     | Low impact; backlog.                                | 168h          |
| TRIVIAL   | Cosmetic/docs.                                      | 336h          |

`sla_due_at` is computed from `config/pilot_stabilization.php` (`severity_sla_hours`).

## Blocking flag

`blocking` defaults to `true` for BLOCKER/CRITICAL and `false` otherwise, unless a
platform admin explicitly overrides it. Accepted risk never silently clears the
blocking flag.

## Tenant/store context

`tenant_id` and `store_id` are **optional** — some defects are global (backend API,
Android app). When `store_id` is present it **must** belong to `tenant_id`; the
service rejects a mismatch.

## Owner assignment

Assign via `POST /pilot-defects/{defect}/assign` with `assigned_to` (or `null` to
unassign). Every assignment appends an `ASSIGNED` event.

## Status lifecycle

`OPEN → IN_PROGRESS → FIXED → RETEST → VERIFIED → CLOSED`, with `ACCEPTED_RISK` as a
governance branch. See [fix-verification-retest-workflow.md](fix-verification-retest-workflow.md)
and [accepted-risk-governance.md](accepted-risk-governance.md).

## Event trail requirement

Every create/update/assign/status-change/severity-change/SLA-breach/accepted-risk/
fix/retest/verify/close appends an immutable row to `pilot_defect_events`. Events are
**append-only** — never updated, never deleted. A CLOSED or VERIFIED defect retains
its full history.

## Evidence reference

Attach an `evidence_reference` (a link/id to a screenshot, log excerpt, or checklist
row). Store the reference, not the raw private data.

## No secrets / no private customer data

Never store passwords, tokens, API keys, gateway secrets, `APP_KEY`, or raw customer
PII in `title`/`description`/`metadata`/`environment`. `PilotDefectService` sanitises
secret-like keys and `key=secret` fragments before persistence.
