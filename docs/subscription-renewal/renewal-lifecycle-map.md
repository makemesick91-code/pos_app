# Renewal Lifecycle Map (Sprint 24)

Lifecycle stages of a subscription renewal candidate. Stages describe **where a
subscription is in its manual renewal review** — they never drive an automatic
mutation.

## Stages

| Stage | Meaning |
|-------|---------|
| `NOT_DUE` | Expiry is beyond the renewal window (or no expiry known). |
| `RENEWAL_WINDOW` | Within `renewal_window_days` of expiry; renewal review begins. |
| `GRACE_PERIOD` | Expired but within `grace_period_days`; manual review, **no auto-suspend**. |
| `OVERDUE` | Expired beyond the grace period; escalation review. |
| `MANUAL_REVIEW` | Flagged for an explicit admin renewal decision. |
| `CLOSED` | Candidate resolved (manually renewed, do-not-renew, or archived). |

## Candidate statuses

`NEW`, `IN_REVIEW`, `DUNNING_PENDING`, `DUNNING_IN_PROGRESS`, `PAYMENT_PENDING`,
`READY_FOR_MANUAL_RENEWAL`, `MANUALLY_RENEWED`, `GRACE_REVIEW`, `OVERDUE_REVIEW`,
`DO_NOT_RENEW`, `ARCHIVED`.

`READY_FOR_MANUAL_RENEWAL` means an admin decision is required — **not** an
automatic subscription mutation.

## Allowed transitions

- `NOT_DUE → RENEWAL_WINDOW` as expiry approaches (run evaluation).
- `RENEWAL_WINDOW → MANUAL_REVIEW` when marked ready for manual renewal.
- `RENEWAL_WINDOW/MANUAL_REVIEW → GRACE_PERIOD` when grace review starts.
- `GRACE_PERIOD → OVERDUE` when the grace period lapses.
- Any stage `→ CLOSED` on a recorded do-not-renew or applied manual renewal.

## Forbidden transitions

- No transition auto-renews a subscription.
- No transition auto-charges a tenant.
- No transition auto-suspends or auto-reactivates a tenant.
- No transition auto-changes a plan or device limit.
- Payment evidence never advances a candidate to a renewed subscription
  automatically.

The only subscription-mutating action is the explicit apply-manual-renewal
decision. See [manual-renewal-decision-playbook.md](manual-renewal-decision-playbook.md).
