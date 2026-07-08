# Onboarding Handover Readiness — Sprint 22

Defines how a won lead is handed from **sales** to **onboarding**. The handover is a
**manual review gate** — it never provisions anything automatically.

## Handover from sales to onboarding

1. Sales marks the lead `ready-for-onboarding`
   (`POST /sales-leads/{lead}/ready-for-onboarding`), which:
   - sets `status = WON_READY_FOR_ONBOARDING`,
   - stamps `ready_for_onboarding_at`,
   - records an `ONBOARDING_HANDOVER_REVIEW` activity.
2. An onboarding reviewer picks up the lead from the ready-for-onboarding list
   (`sales-pipeline:lead-summary` → `ready_for_onboarding`).

## Data needed before tenant creation

- Confirmed business name and legal entity (if any).
- Confirmed primary contact (name + email/phone).
- Selected package / plan intent (see `saas-package-catalog.md`).
- Store and device count estimate.
- Any pricing / billing expectation captured as activity notes.

## Manual approval gate

- Tenant creation remains the existing **platform-admin tenant onboarding** flow
  (Sprint 12), triggered manually **after** the onboarding reviewer approves.
- Sprint 22 draws the line explicitly: **no auto provisioning** from a lead.

## No auto provisioning

- `ready_for_onboarding_at` is a signal only. There is no automatic creation of a
  tenant, user, subscription, or device from the sales pipeline.

## Future sprint integration placeholder

- A future sprint may wire an **explicit, human-approved** "convert ready lead →
  tenant onboarding run" action that reuses the Sprint 12 onboarding service. That
  action will remain manually approved and audit-logged; it is **out of scope** for
  Sprint 22.
