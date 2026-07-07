# Pilot Closure Checklist

Sprint 18 — Pilot Closure & Production Handover Foundation.

This checklist drives a pilot closure run (`pilot_closure_runs`). Run
`php artisan pilot:closure-check --json` to produce the aggregate decision. All
values are aggregate references only — never secrets, never raw customer data.

## Closure inputs

- [ ] Pilot candidate commit recorded (reference only, e.g. `773f017`).
- [ ] Pilot candidate GO tag recorded (reference only, e.g. `sprint-17-...-go`).
- [ ] Field trial summary reviewed (`pilot:field-trial-summary`).
- [ ] Monitoring / hypercare summary reviewed (`pilot:health-summary`,
      `hypercare:issue-triage`).
- [ ] Stabilization / defect summary reviewed (`pilot:stabilization-go-no-go`).

## Final reviews

- [ ] **Final defect review** — no open BLOCKER/CRITICAL without valid accepted
      risk; open MAJOR mitigated or accepted. See
      [final-defect-closure-summary.md](final-defect-closure-summary.md).
- [ ] **Accepted risk review** — every accepted risk has approver + reason +
      (for blocking/major) an unexpired expiry/review date. See
      [accepted-risk-final-review.md](accepted-risk-final-review.md).
- [ ] **Operator feedback review** — operator feedback log triaged
      (`docs/pilot/operator-feedback-log.md`).

## Closure decision

| Decision | Meaning |
|----------|---------|
| GO | Final defect + accepted-risk + stabilization all GO. |
| WATCH | Non-blocking warnings, open MAJOR, or valid accepted risk. |
| NO_GO | Open blocking defect without valid accepted risk, or expired blocking accepted risk. |

- [ ] Closure decision recorded in the closure run.
- [ ] Closure approved (`APPROVED`) or blocked (`BLOCKED`) by platform admin.

## Non-goals (Sprint 18)

- No automatic production deploy.
- No real Slack / WhatsApp / email alert sending.
- No secrets, APK/AAB, keystore, or `.env` committed.
