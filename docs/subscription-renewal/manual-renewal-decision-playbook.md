# Manual Renewal Decision Playbook (Sprint 24)

Every subscription renewal in Sprint 24 is an explicit, manual, audit-logged admin
decision. Payment evidence never renews a subscription automatically.

## Decisions

| Decision | Meaning |
|----------|---------|
| `APPROVE_MANUAL_RENEWAL` | Approve renewal; may be manually applied. |
| `APPROVE_WITH_RISK` | Approve with a documented, accepted risk; may be manually applied. |
| `REJECT_RENEWAL` | Do not renew this cycle. |
| `DEFER_REVIEW` | Postpone the decision. |
| `DO_NOT_RENEW` | Permanently do not renew. |

Decision statuses: `DRAFT`, `RECORDED`, `APPLIED_MANUALLY`, `VOIDED`.

## Recording a decision

`POST /api/v1/admin/subscription-renewal/candidates/{candidate}/decisions`

Recording a decision **never** mutates a `TenantSubscription`. It captures
governance intent and writes a renewal activity + admin audit log entry.

## Optional manual apply

`POST /api/v1/admin/subscription-renewal/decisions/{decision}/apply-manual-renewal`

This is the **only** subscription-mutating action in Sprint 24. It is explicit,
`platform.admin`-only and audit-logged. `SubscriptionRenewalDecisionService::applyManualRenewalDecision()`
enforces:

1. The decision status is `RECORDED`.
2. The decision is `APPROVE_MANUAL_RENEWAL` or `APPROVE_WITH_RISK`.
3. A decider (`decided_by_user_id`) is present.
4. Both `effective_start_date` and `effective_end_date` are present.

When applied, it extends the subscription period (`starts_at` / `ends_at`) and sets
the subscription `ACTIVE`. It does **not**:

- call any payment gateway or billing payment gateway;
- change the subscription plan;
- change the device limit;
- trigger from payment evidence automatically.

## Audit logging

- Every decision record, void, and manual apply is written to `admin_audit_logs`.
- Snapshots are sanitized; no secrets are persisted.

## No payment-evidence auto-renewal

Accepting a Sprint 23 billing payment evidence updates an invoice's paid/remaining
amounts only. It **never** advances a renewal candidate to a renewed subscription.
Renewal always requires this manual decision path.
