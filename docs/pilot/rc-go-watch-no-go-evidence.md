# Pilot RC GO / WATCH / NO-GO Evidence

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

Record the evidence behind the pilot release candidate decision. Attach command
output as **redacted** JSON (no secrets). This document authorizes a **pilot**,
not a production deploy.

## RC candidate

| Field | Value |
|-------|-------|
| RC candidate commit | ____________ |
| Branch | ____________ |
| GO tag candidate | `sprint-14-pilot-release-candidate-operator-uat-foundation-go` |
| Date | ____________ |
| Approver | ____________ (placeholder until sign-off) |

## Evidence

### Backend tests
- Command: `cd backend && php artisan test`
- Result: ____________ (pass/fail, counts)

### Android CI
- `:app:assembleDebug`: ____________ (pass/fail, run id)
- `:app:testDebugUnitTest`: ____________ (pass/fail, run id)

### Release readiness (Sprint 13)
- `php artisan production:readiness-check --json`: ____________ (overall_status)
- `php artisan release:go-no-go --json`: ____________ (decision)

### Pilot gate (Sprint 14)
- `php artisan pilot:rc-check --json`: ____________ (decision)
- `php artisan pilot:uat-summary --json`: ____________ (decision, totals)

### Open issues
- BLOCKER/CRITICAL open: ____________ (count — must be 0 for GO)
- MAJOR open: ____________ (count — WATCH unless accepted)
- MINOR/TRIVIAL open: ____________

## Risk notes

- ____________ (required when decision is WATCH; include follow-up actions)

## Decision

> **Decision: GO / WATCH / NO-GO** (circle one)

- Rationale: ____________
- Follow-up actions (WATCH): ____________
- Approver placeholder: ________________________  Date: ____________

## Decision rules (reference)

- **GO** — all gates pass, no open BLOCKER/CRITICAL.
- **WATCH** — only non-critical warnings; documented risk + follow-up.
- **NO-GO** — any failing gate or open BLOCKER/CRITICAL, backend tests failing,
  or Android CI red.
