# Commercial Launch Checklist — Aish POS Lite

Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation.

This checklist governs whether the product is commercially launch-ready. It is
evidence-based and admin-only. It does **not** open public signup, collect real
billing, or deploy to production.

## Launch candidate

- Launch candidate commit: `dc1d9c7` (or later main after Sprint 19 GO tag)
- Launch candidate tag: `sprint-19-production-operations-post-handover-governance-foundation-go`
- Commercial launch run reference: `LAUNCH-YYYYMMDD-XXXXXX` (recorded via admin API)

## Prior gate results (must all pass)

| Gate | Command(s) | Result |
| --- | --- | --- |
| Release readiness | `production:readiness-check`, `release:go-no-go` | ☐ |
| Pilot RC / UAT | `pilot:rc-check`, `pilot:uat-summary` | ☐ |
| Deployment / field | `pilot:deployment-check`, `pilot:field-trial-summary` | ☐ |
| Monitoring / hypercare | `pilot:daily-monitoring-check`, `pilot:health-summary`, `hypercare:issue-triage` | ☐ |
| Stabilization / defect | `pilot:defect-summary`, `pilot:burndown-summary`, `pilot:sla-check`, `pilot:stabilization-go-no-go` | ☐ |
| Closure / handover | `pilot:closure-check`, `production:handover-summary`, `production:signoff-summary`, `production:handover-go-no-go` | ☐ |
| Production operations | `production:ops-health`, `production:incident-summary`, `production:backup-governance-check`, `production:post-handover-go-no-go` | ☐ |

## Commercial readiness

- [ ] Operations baseline result is GO/WATCH (`production:post-handover-go-no-go`)
- [ ] Package catalog readiness — at least one ACTIVE package covering `GENERAL_UMKM`
- [ ] Pricing governance — active packages carry price/currency/device-limit metadata
- [ ] Sales enablement readiness — offer sheet / FAQ / proposal handoff present
- [ ] Onboarding capacity — weekly capacity configured for each active onboarding level
- [ ] Risk review — no open CRITICAL/HIGH commercial risk without valid accepted risk
- [ ] Signoff review — OWNER / TECHNICAL / SALES / OPERATIONS approved, none REJECTED

## Commercial decision

Run `php artisan commercial:launch-go-no-go --json`.

- **GO** — all signals pass.
- **WATCH** — open MEDIUM risk with mitigation, approved-with-risk signoff, or a
  non-critical package/pricing/onboarding warning.
- **NO-GO** — any required gate/command/doc missing, no active package, blocking
  pricing/onboarding issue, open CRITICAL/HIGH risk without valid accepted risk,
  or a rejected signoff.

See [commercial-go-watch-no-go-report.md](commercial-go-watch-no-go-report.md).
