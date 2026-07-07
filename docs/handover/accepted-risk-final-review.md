# Accepted Risk Final Review

Sprint 18 — Pilot Closure & Production Handover Foundation.

Produced by `AcceptedRiskFinalReviewService`. Every ACCEPTED_RISK defect must
carry an approver, a reason, and (for BLOCKER/CRITICAL/MAJOR) an unexpired
expiry/review date. Accepted risk changes gating impact but **never the recorded
severity**.

## Accepted risk register template

| Defect ref | Original severity | Approver | Reason | Expiry / review date | State | Final action owner |
|------------|-------------------|----------|--------|----------------------|-------|--------------------|
| DEF-… | CRITICAL | _role_ | _reason_ | YYYY-MM-DD | valid / expired / incomplete | _role_ |

## Rules

- **Approver required** — missing approver ⇒ incomplete ⇒ WATCH.
- **Reason required** — missing reason ⇒ incomplete ⇒ WATCH.
- **Expiry required** for BLOCKER/CRITICAL/MAJOR — missing ⇒ incomplete ⇒ WATCH.
- **Expired blocking acceptance** (BLOCKER/CRITICAL past expiry) ⇒ **NO_GO**.
- **Non-blocking expired / any valid acceptance** ⇒ WATCH.
- No accepted-risk defects ⇒ GO.

## Decision impact

| Condition | Decision |
|-----------|----------|
| Expired acceptance on a blocking-severity defect | NO_GO |
| Any valid, incomplete, or non-blocking expired acceptance | WATCH |
| No accepted-risk defects | GO |

## Final action owner

Each accepted risk names a **final action owner** responsible for closing it
post-handover. Record in the register above.
