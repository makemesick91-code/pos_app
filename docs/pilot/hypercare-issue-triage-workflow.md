# Hypercare Issue Triage Workflow

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> No real credentials or real customer data in this workflow.

Defines how field issues raised during the pilot hypercare window are intook,
classified, owned, tracked against an SLA, and gated. Backed by
`hypercare:issue-triage` and `docs/pilot/field-issue-severity-sla.md`.

## 1. Intake

- Source: operator feedback, daily monitoring signal FAIL/WARN, smoke checklist.
- Record in `docs/pilot/field-issue-register.md` (Sprint 15) with a stable ID.
- Capture area (sales, payment/QRIS, sync, receipt, inventory, report, closing,
  subscription/device, admin/onboarding), reproduction, and evidence.
- Never record real passwords, secrets, or private customer data — anonymize.

## 2. Severity assignment

Assign one of `BLOCKER | CRITICAL | MAJOR | MINOR | TRIVIAL` per
`field-issue-severity-sla.md`.

## 3. Blocking flag

- `BLOCKER` / `CRITICAL` → blocking = yes → forces **NO-GO** while open.
- `MAJOR` → blocking = usually → forces **WATCH** while open.
- `MINOR` / `TRIVIAL` → non-blocking.

## 4. Owner assignment

Assign a single owner (placeholder, e.g. `hypercare-lead@example.test`). One
owner per issue.

## 5. SLA target

Set the initial response + resolution target from the severity/SLA table.

## 6. Status updates

Allowed statuses: `OPEN → IN_PROGRESS → FIXED → RETEST → CLOSED`, plus
`ACCEPTED_RISK`. Only `OPEN`, `IN_PROGRESS`, `RETEST` count as "open" for gating.

## 7. Retest workflow

- Move `FIXED` issues to `RETEST` with a linked verification scenario.
- On pass → `CLOSED`. On fail → back to `OPEN`/`IN_PROGRESS`.

## 8. Accepted risk workflow

- `ACCEPTED_RISK` requires a documented risk note and explicit approver.
- BLOCKER/CRITICAL may only be accepted as risk when explicitly out of scope
  with a documented rationale (see `hypercare-go-watch-no-go-report.md`).

## 9. Escalation rules

- Open BLOCKER not moving within its SLA → escalate to release owner same day.
- Two or more open CRITICAL → pause pilot expansion; NO-GO stands.
- Repeated MAJOR in one area → treat area as WATCH until root-caused.
