# Final Defect Closure Summary

Sprint 18 — Pilot Closure & Production Handover Foundation.

Produced by `FinalDefectReviewService` (reuses the Sprint 17 defect governance).
Run `php artisan pilot:closure-check --json` and read `final_defect_summary`.
The **original severity is always preserved** — accepted risk never hides it.

## Aggregate template

| Metric | Value |
|--------|-------|
| Total defects | _n_ |
| Open (OPEN/IN_PROGRESS/FIXED/RETEST) | _n_ |
| Open BLOCKER/CRITICAL (unresolved, no valid risk) | _n_ |
| Open MAJOR | _n_ |
| Fixed | _n_ |
| Retest | _n_ |
| Verified | _n_ |
| Closed | _n_ |
| SLA-breached (open) | _n_ |
| Accepted risk | _n_ |
| Accepted risk — expired blocking | _n_ |

## Decision impact

| Condition | Decision |
|-----------|----------|
| Open BLOCKER/CRITICAL without valid accepted risk | NO_GO |
| Expired blocking accepted risk | NO_GO |
| Open MAJOR, or valid accepted risk | WATCH |
| Only MINOR/TRIVIAL open, or nothing open | GO |

## Final closure status

- [ ] All unresolved blocking defects closed or validly accepted.
- [ ] SLA-breached defects reviewed.
- [ ] Retest/verification state recorded.
- [ ] Final closure status: **GO / WATCH / NO_GO** (record here).
