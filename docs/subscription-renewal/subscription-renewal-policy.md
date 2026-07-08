# Subscription Renewal Policy (Sprint 24)

Manual, admin-governed renewal policy for the Aish POS Lite SaaS. A renewal policy
is **governance metadata only** — it never sends anything, never charges, and never
suspends a tenant.

## Policy fields

| Field | Default | Meaning |
|-------|---------|---------|
| `renewal_window_days` | 14 | Days before expiry a subscription enters the renewal window. |
| `grace_period_days` | 7 | Days after expiry a subscription stays in grace (manual review, no auto-suspend). |
| `dunning_start_days_before_expiry` | 7 | Earliest a manual dunning reminder may be queued. Must be ≤ renewal window. |
| `max_manual_dunning_notices` | 3 | Maximum active manual dunning notices per candidate. |
| `requires_manual_approval` | true | Renewal always requires an explicit manual admin decision. |

Statuses: `ACTIVE`, `INACTIVE`, `ARCHIVED`.

The default policy is `DEFAULT_MANUAL_RENEWAL`, ensured idempotently via
`POST /api/v1/admin/subscription-renewal/policies/ensure-default` or
`SubscriptionRenewalPolicyService::ensureDefault()`.

## Window validation

- All windows must be non-negative.
- `dunning_start_days_before_expiry` must not exceed `renewal_window_days`.
- `max_manual_dunning_notices` must be at least 1.

## Hard guardrails (Sprint 24)

The policy governs review timing only. It **must not**:

- trigger a real payment gateway or auto-charge;
- automate subscription payment collection;
- generate a public payment link or renewal portal;
- auto-suspend or auto-reactivate a tenant;
- auto-renew a subscription without an explicit manual admin decision;
- auto-change a subscription plan or device limit;
- send a real email / WhatsApp / SMS / Slack message.

Subscription renewal is lifecycle governance over `TenantSubscription`. It is
distinct from tenant POS QRIS/cash customer payments and from the Sprint 23 SaaS
billing collection invoice/payment-evidence domain.

See [renewal-lifecycle-map.md](renewal-lifecycle-map.md) and
[manual-renewal-decision-playbook.md](manual-renewal-decision-playbook.md).
