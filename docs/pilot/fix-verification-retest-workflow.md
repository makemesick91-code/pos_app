# Fix Verification / Retest Workflow (Sprint 17)

Driven by `FixVerificationService` and the endpoints
`POST /pilot-defects/{defect}/mark-fixed` and `POST /pilot-defects/{defect}/verify`.

## Fixed status meaning

`FIXED` means a developer believes the defect is resolved and it is ready for retest.
**FIXED is not CLOSED.** `mark-fixed` stamps `fixed_at` and appends a `FIXED` event.

## Retest requested

Move the defect to `RETEST` (`PilotDefectService::transitionStatus` /
`requestRetest`) to signal QA/operator that a retest is queued. Appends a
`RETEST_REQUESTED` event.

## Retest pass / fail

`POST /pilot-defects/{defect}/verify` with `passed` (boolean) records the outcome
explicitly:

- Always sets `verification_result` (`PASS`/`FAIL`), `verified_by`, `verified_at`, and
  appends a `VERIFIED` event.
- **PASS** → status becomes `VERIFIED` (and `CLOSED` when `close=true`).
- **FAIL** → status returns to `IN_PROGRESS` (the defect is reopened for more work).

## Verified

`VERIFIED` means the retest passed and the fix is confirmed. The defect may then be
closed.

## Closed

`CLOSED` stamps `closed_at`. History is preserved; a closed defect keeps its full event
trail.

## Failed retest handling

A failed verification never closes the defect — it re-enters `IN_PROGRESS` so the fix
can be reworked and re-verified. The failure is recorded in the event trail.

## Evidence required

Attach `evidence_reference` to `mark-fixed` and `verify` (a link/id to the retest
evidence). Store the reference, never the raw private data.
