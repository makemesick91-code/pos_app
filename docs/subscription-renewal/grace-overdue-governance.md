# Grace & Overdue Governance (Sprint 24)

How the platform admin team manually handles subscriptions that pass their renewal
date. **Nothing here suspends or reactivates a tenant automatically.**

## Grace review

- Triggered when a subscription expires but is within `grace_period_days`.
- Candidate moves to status `GRACE_REVIEW`, stage `GRACE_PERIOD`.
- Finance/Support review the account, queue manual dunning notices, and record a
  renewal decision.
- Tenant access is **not** changed by the system during grace.

## Overdue review

- Triggered when a subscription is expired beyond the grace period.
- Candidate moves to status `OVERDUE_REVIEW`, stage `OVERDUE`.
- Escalation activity is recorded; a renewal risk may be opened.
- Any decision to stop service is a **manual operational action** taken outside
  Sprint 24 automation.

## Escalation

- Use a renewal activity of type `ESCALATION` to record hand-off to
  Finance/Operations/Owner.
- Open a `SubscriptionRenewalRisk` in area `DUNNING_SLA`, `CUSTOMER_CHURN` or
  `PAYMENT_DELAY` when SLA is at risk.

## Risk review

- Open CRITICAL/HIGH risk without a valid accepted risk → NO-GO.
- Open MEDIUM risk → WATCH.
- See [subscription-renewal-risk-register.md](subscription-renewal-risk-register.md).

## Approvals

- Grace/overdue outcomes require manual Support/Finance approval recorded as a
  renewal decision and, for a GO, the required sign-offs.
- No auto-suspension. No auto-reactivation. No auto-renewal.
