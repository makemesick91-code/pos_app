# Sales Pipeline Risk Register — Sprint 22

Tracks sales pipeline risks and their governance impact. Backed by
`sales_pipeline_risks` and `SalesPipelineRiskGovernanceService`.

## Severity

| Severity | Decision impact (open, unaccepted) |
| -------- | ---------------------------------- |
| `CRITICAL` | **NO-GO** |
| `HIGH` | **NO-GO** |
| `MEDIUM` | **WATCH** |
| `LOW` | Informational |
| `INFO` | Informational |

## Area

`LEAD_QUALITY`, `PRICING_EXPECTATION`, `PACKAGE_MISALIGNMENT`,
`ONBOARDING_CAPACITY`, `LEGAL_PRIVACY`, `PAYMENT_BILLING_EXPECTATION`,
`DATA_QUALITY`, `FOLLOW_UP_SLA`, `OPERATIONS`, `OTHER`.

## Status

`OPEN` → `MITIGATED` / `ACCEPTED_RISK` / `CLOSED`.

## Owner

Each risk should carry an `owner_user_id`. Free-text (`title`, `description`,
`mitigation`) is sanitized — no secrets or private customer data.

## Mitigation

- An open `MEDIUM` risk **with** a non-empty `mitigation` is still a WATCH signal.
- Provide a clear mitigation plan so reviewers understand the residual exposure.

## Accepted risk

- Accepting a `CRITICAL` / `HIGH` / `MEDIUM` risk requires:
  - a **reason**,
  - an **approver**,
  - an **expiry / review date** (`accepted_risk_expires_at`).
- An **expired** accepted risk re-blocks: `CRITICAL`/`HIGH` → NO-GO, `MEDIUM` →
  WATCH.

## Expiry

- Accepted risks must be re-reviewed on or before their expiry date. Expiry keeps
  accepted risks from silently masking exposure forever.

## Decision impact summary

- Any open `CRITICAL`/`HIGH` without a valid accepted risk → **NO-GO**.
- Any open `MEDIUM` (mitigated or not) → **WATCH**.
- Closed risks do not affect the decision.
