# Customer Onboarding Capacity — Aish POS Lite

Sprint 20. Aggregate onboarding capacity governance. Evaluated by
`OnboardingCapacityService` and `commercial:onboarding-capacity`. Uses aggregate
placeholders only — it never creates real tenants and never uses real customer
data.

## Weekly capacity by onboarding level

| Onboarding level | Customers / week (placeholder) | Description |
| --- | --- | --- |
| SELF_GUIDED | 10 | Customer follows docs; minimal hand-holding. |
| ASSISTED | 5 | Remote-assisted setup session. |
| MANAGED | 2 | Full managed onboarding by the team. |

Configured in `config/commercial_launch.php` under `onboarding_capacity`.

## Self-guided flow

1. Admin provisions tenant (Sprint 12 onboarding).
2. Customer receives self-guided setup docs.
3. Demo/reset data available for practice.

## Assisted flow

1. Admin provisions tenant + demo data.
2. Scheduled remote session for product/QRIS/printer setup.
3. Handover to operator.

## Managed flow

1. Admin provisions tenant + demo data.
2. Team completes full configuration.
3. Operator + admin handover with evidence.

## References

- Demo tenant setup / reset — Sprint 12 tenant onboarding & demo data foundation.
- Operator / admin handover — `docs/handover/production-handover-pack.md`.
- Support readiness — `docs/operations/support-sla-operations.md`.

## Decision

- **NO-GO** — onboarding capacity doc missing, or an active package uses an
  onboarding level with zero configured weekly capacity.
- **WATCH** — total configured weekly capacity is zero.
- **GO** — capacity configured for every active package's onboarding level.
