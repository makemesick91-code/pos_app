# Field Issue Severity & SLA

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.

Canonical severity levels, SLA targets, and decision impact used by
`hypercare:issue-triage` and the hypercare triage workflow.

## Severity / SLA table

| Severity | Meaning | Blocking | Initial Response Target | Resolution Target | Decision Impact |
|----------|---------|----------|-------------------------|-------------------|-----------------|
| BLOCKER | Pilot cannot continue | Yes | Same day | Immediate / before continue | NO-GO |
| CRITICAL | Core flow broken | Yes | Same day | Urgent | NO-GO |
| MAJOR | Workaround exists but impacts pilot | Usually | 1 business day | Planned fix | WATCH |
| MINOR | Low impact | No | 2 business days | Backlog | GO/WATCH |
| TRIVIAL | Cosmetic/docs | No | Backlog | Backlog | GO |

## Status lifecycle

`OPEN → IN_PROGRESS → FIXED → RETEST → CLOSED` plus `ACCEPTED_RISK`.

Open (gating) statuses: `OPEN`, `IN_PROGRESS`, `RETEST`.
Closed/non-gating statuses: `FIXED`, `CLOSED`, `ACCEPTED_RISK`.

## Gating rules

- Any open `BLOCKER`/`CRITICAL` → **NO-GO**.
- Any open `MAJOR` → **WATCH** (unless explicitly accepted with mitigation).
- Only `MINOR`/`TRIVIAL` (or none) open → **GO**.
- `ACCEPTED_RISK` requires documented rationale + approver and does not count
  as open.

## Notes

- Severity is assigned by impact on the core pilot flow, not by frequency.
- SLA targets are governance placeholders for the pilot; adjust per engagement.
