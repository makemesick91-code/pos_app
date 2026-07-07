# Pilot Stabilization GO / WATCH / NO-GO Report (Sprint 17)

Produced by `php artisan pilot:stabilization-go-no-go [--json] [--strict]` and
`PilotStabilizationReportService::evaluate()`. Read-only; never prints secrets, never
sends alerts, never runs Android Gradle.

## Candidate commit / tag

- Candidate commit: `__________`
- Candidate tag: `__________`

## Monitoring window

- Window start: `__________`
- Window end: `__________`

## Release gate result (Sprint 13)

`production:readiness-check` + `release:go-no-go` — result: `__________`

## RC / UAT result (Sprint 14)

`pilot:rc-check` + `pilot:uat-summary` — result: `__________`

## Deployment / field result (Sprint 15)

`pilot:deployment-check` + `pilot:field-trial-summary` — result: `__________`

## Monitoring / hypercare result (Sprint 16)

`pilot:daily-monitoring-check` + `pilot:health-summary` + `hypercare:issue-triage` —
result: `__________`

## Defect summary (Sprint 17)

`pilot:defect-summary` — open BLOCKER / CRITICAL / MAJOR counts and decision.

## Burn-down summary

`pilot:burndown-summary` — total / open / fixed / retest / verified / closed and
decision.

## SLA breach summary

`pilot:sla-check` — open SLA-breached defect count by severity.

## Accepted-risk summary

Accepted-risk count, and blocking accepted-risk validity (valid vs expired).

## Verification / retest summary

Fixed / retest / verified / closed counts.

## Decision: GO / WATCH / NO-GO

- **NO-GO** — any blocking stabilization signal fails, or the burn-down is NO-GO
  (open blocker/critical without valid accepted risk, or expired accepted-risk
  blocker/critical).
- **WATCH** — no blocking failure, but the burn-down is WATCH (open major or validly
  accepted blocking defect) or a warning exists.
- **GO** — every signal passes and the burn-down is GO.

Decision: `__________`

## Approver

- Stabilization owner: `__________`
- Date: `__________`
