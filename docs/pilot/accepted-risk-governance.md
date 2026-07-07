# Accepted-Risk Governance (Sprint 17)

Governed by `AcceptedRiskGovernanceService` and
`POST /api/v1/admin/pilot-defects/{defect}/accept-risk`.

## When accepted risk is allowed

A defect may be accepted as a known risk when the team consciously decides to proceed
with the pilot despite the open defect (e.g. a workaround exists, or the fix is out of
pilot scope). Accepting risk moves the defect to `ACCEPTED_RISK` status.

## Required approver

An approver is mandatory. Supply `approver_id` (or the acting platform admin is
recorded). The approver is stored in `accepted_risk_by` and echoed in the
`ACCEPTED_RISK` event payload.

## Required reason

A non-empty `reason` is mandatory and stored in `accepted_risk_reason`.

## Required expiry / review date

For **BLOCKER, CRITICAL, and MAJOR** severities an `expires_at` (expiry/review date) is
**required** (`accepted_risk_requires_expiry_for` in config). MINOR/TRIVIAL may omit it.
An expired acceptance is treated as **no longer valid**.

## Decision impact

- A **validly accepted** blocking-severity defect downgrades the burn-down from NO-GO
  to **WATCH** (config `accepted_risk_downgrades_blocking_to_watch = true`).
- An **expired** accepted-risk blocking defect forces **NO-GO** again.
- Accepted risk **never** produces GO for a blocking defect, and never silently flips a
  critical defect to healthy.

## Cannot hide original severity

Accepting risk **preserves** the original `severity` and `blocking` flag. It only sets
the accepted-risk governance fields and the status. The register, resources, burn-down,
and events always continue to show how severe the defect really is.
