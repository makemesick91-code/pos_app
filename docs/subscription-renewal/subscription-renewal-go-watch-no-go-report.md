# Subscription Renewal GO / WATCH / NO-GO Report (Sprint 24)

Evidence-backed decision record for the Sprint 24 subscription renewal & dunning
governance foundation.

## Candidate

- **Candidate commit:** recorded at merge (`feature/sprint-24-subscription-renewal-dunning-governance-foundation`).
- **Candidate GO tag:** `sprint-24-subscription-renewal-dunning-governance-foundation-go`.
- **Previous GO tag:** `sprint-23-billing-collection-governance-foundation-go`.

## Previous gates

| Gate | Command(s) | Expected |
|------|-----------|----------|
| Release (S13) | `production:readiness-check`, `release:go-no-go` | registered |
| RC/UAT (S14) | `pilot:rc-check`, `pilot:uat-summary` | registered |
| Deployment/field (S15) | `pilot:deployment-check`, `pilot:field-trial-summary` | registered |
| Monitoring/hypercare (S16) | `pilot:daily-monitoring-check`, `pilot:health-summary`, `hypercare:issue-triage` | registered |
| Stabilization (S17) | `pilot:defect-summary`, `pilot:burndown-summary`, `pilot:sla-check`, `pilot:stabilization-go-no-go` | registered |
| Closure/handover (S18) | `pilot:closure-check`, `production:handover-summary`, `production:signoff-summary`, `production:handover-go-no-go` | registered |
| Operations (S19) | `production:ops-health`, `production:incident-summary`, `production:backup-governance-check`, `production:post-handover-go-no-go` | registered |
| Commercial launch (S20) | `commercial:launch-readiness`, `commercial:package-summary`, `commercial:onboarding-capacity`, `commercial:launch-go-no-go` | registered |
| Public website (S21) | `public-website:readiness`, `public-website:content-summary`, `public-website:lead-summary`, `public-website:go-no-go` | registered |
| Sales pipeline (S22) | `sales-pipeline:readiness`, `sales-pipeline:lead-summary`, `sales-pipeline:activity-summary`, `sales-pipeline:go-no-go` | registered |
| Billing collection (S23) | `billing-collection:readiness`, `billing-collection:invoice-summary`, `billing-collection:collection-summary`, `billing-collection:go-no-go` | registered |

## Subscription renewal readiness

`php artisan subscription-renewal:readiness --json` reports PASS/WARN/FAIL across:
config guardrails, docs, policy governance, run/candidate governance, dunning
manual-only governance, decision governance, risk governance, sign-off governance.

## Summaries

- `php artisan subscription-renewal:candidate-summary --json`
- `php artisan subscription-renewal:dunning-summary --json`
- Decision summary: recorded renewal decisions by type/status.
- Risk summary: open by severity, blocking/watch counts.
- Sign-off summary: approving roles, missing roles, rejected, approved-with-risk.

## Final decision

The aggregate decision comes from `php artisan subscription-renewal:go-no-go --json`.

- **GO** — all prior gates registered, docs present, no automation guardrail
  enabled, default policy active, no blocking risk, required sign-offs valid.
- **WATCH** — an open MEDIUM risk, an approved-with-risk sign-off, a missing
  sign-off role, or a missing default policy.
- **NO_GO** — a missing gate/doc, an enabled automation guardrail, an open
  CRITICAL/HIGH risk without a valid accepted risk, or a rejected sign-off.

> On a fresh CI database there are no recorded sign-offs, so the correct aggregate
> decision is WATCH/NO_GO. A real GO requires sign-offs recorded via the admin API.
