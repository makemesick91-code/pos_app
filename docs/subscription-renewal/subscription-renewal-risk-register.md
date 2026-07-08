# Subscription Renewal Risk Register (Sprint 24)

Risk governance for subscription renewal & dunning readiness. Open blocking risk
forces NO-GO; open watch risk forces WATCH.

## Severity

`CRITICAL`, `HIGH`, `MEDIUM`, `LOW`, `INFO`.

- Blocking (NO-GO): `CRITICAL`, `HIGH`.
- Watch (WATCH): `MEDIUM`.

## Areas

`PAYMENT_DELAY`, `GRACE_PERIOD`, `RENEWAL_APPROVAL`, `CUSTOMER_CHURN`,
`BILLING_MISMATCH`, `PLAN_MISMATCH`, `DEVICE_LIMIT_IMPACT`, `LEGAL_PRIVACY`,
`DUNNING_SLA`, `OPERATIONS`, `OTHER`.

## Fields

- **owner** — accountable user.
- **mitigation** — what is being done. A mitigated MEDIUM still reads as WATCH.
- **accepted risk** — approver + reason + expiry required for CRITICAL/HIGH/MEDIUM.
- **expiry** — an accepted risk past its expiry re-blocks (NO-GO for CRITICAL/HIGH,
  WATCH for MEDIUM).

## Status

`OPEN`, `MITIGATED`, `ACCEPTED_RISK`, `CLOSED`.

## Decision impact

| Condition | Decision |
|-----------|----------|
| Open CRITICAL/HIGH, no valid accepted risk | NO_GO |
| Expired accepted CRITICAL/HIGH | NO_GO |
| Open MEDIUM (mitigated or not) | WATCH |
| Expired accepted MEDIUM | WATCH |
| Only closed / valid accepted risks | GO |

## Sign-off

Required roles: `OWNER`, `FINANCE`, `SALES`, `OPERATIONS`, `LEGAL_PRIVACY`,
`TECHNICAL`, `SUPPORT`. A rejected sign-off → NO-GO; an approved-with-risk sign-off
or a missing role → WATCH.
