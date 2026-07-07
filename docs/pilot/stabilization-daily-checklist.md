# Pilot Stabilization Daily Checklist (Sprint 17)

Run once per pilot day during stabilization. All commands are read-only and safe.

## 1. Daily defect review

- [ ] `php artisan pilot:defect-summary --json` — review open BLOCKER/CRITICAL/MAJOR.
- [ ] Triage any new defects into the register with severity/area/owner.

## 2. SLA breach check

- [ ] `php artisan pilot:sla-check --json` — review overdue open defects.
- [ ] (Optional, explicit) `pilot:sla-check --mark-breached` to flag + event overdue
      defects. Never run in CI.

## 3. Retest queue check

- [ ] Review defects in `FIXED` / `RETEST` status.
- [ ] Verify passed fixes (`verify passed=true`), reopen failed ones (`passed=false`).

## 4. Accepted-risk review

- [ ] Review `ACCEPTED_RISK` defects; confirm none have an expired `expires_at`.
- [ ] Re-open or re-accept any expired blocking acceptances.

## 5. Burn-down review

- [ ] `php artisan pilot:burndown-summary --json` — record the day's row in
      [defect-burndown-report.md](defect-burndown-report.md).

## 6. Operator feedback review

- [ ] Review [operator-feedback-log.md](operator-feedback-log.md) for new issues.

## 7. Decision checkpoint

- [ ] `php artisan pilot:stabilization-go-no-go --json` — record GO/WATCH/NO-GO in
      [stabilization-go-watch-no-go-report.md](stabilization-go-watch-no-go-report.md).
