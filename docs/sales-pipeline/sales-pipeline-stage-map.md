# Sales Pipeline Stage Map — Sprint 22

Canonical pipeline stages and allowed transitions. Stages are governance metadata
(`sales_pipeline_stages`); a stage never provisions anything.

## Canonical stages

| Order | Stage code | Meaning | Terminal |
| ----- | ---------- | ------- | -------- |
| 10 | `NEW` | Freshly captured or imported lead | No |
| 20 | `CONTACTED` | First outreach made (manual) | No |
| 30 | `QUALIFIED` | Meets qualification criteria | No |
| 40 | `DEMO_SCHEDULED` | Demo planned (manual note) | No |
| 50 | `PROPOSAL_SENT` | Proposal shared (manual note) | No |
| 60 | `NEGOTIATION` | Commercial discussion in progress | No |
| 70 | `WON_READY_FOR_ONBOARDING` | Won — ready for **manual onboarding review** | Yes |
| 80 | `LOST` | Closed lost | Yes |
| 90 | `ARCHIVED` | Archived / dormant | Yes |

Run `POST /api/v1/admin/sales-pipeline/stages/ensure-defaults` (idempotent) to seed
the canonical set.

## Allowed transitions

- Forward flow: `NEW → CONTACTED → QUALIFIED → DEMO_SCHEDULED → PROPOSAL_SENT →
  NEGOTIATION → WON_READY_FOR_ONBOARDING`.
- A lead may move to `LOST` or `ARCHIVED` from any non-terminal stage.
- Transitions are conservative: transitioning to a stage aligns the lead's flat
  `status` where a matching status exists.

## Terminal stages

- `WON_READY_FOR_ONBOARDING` means a **manual onboarding review** is due. It does
  **not** create a tenant, user, subscription, or device.
- `LOST` / `ARCHIVED` are closed states. Re-opening a terminal lead requires an
  explicit activity note (recorded via `SalesLeadActivityService`) so the history
  is auditable — a lead is never silently reopened.

## Ready-for-onboarding meaning

Ready-for-onboarding is a **handoff signal to a human onboarding reviewer**. Actual
tenant provisioning remains a separate, manually-approved step in a future sprint
(see `onboarding-handover-readiness.md`).
