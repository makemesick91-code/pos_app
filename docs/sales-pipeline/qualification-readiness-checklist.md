# Qualification Readiness Checklist — Sprint 22

A lead is qualification-ready when the essential intake data and at least one
completed activity exist. The score is **advisory** and never bypasses manual
approval.

## Scoring (advisory, 0–100)

| Signal | Weight |
| ------ | ------ |
| Business name present | 15 |
| Contact name present | 10 |
| Contact email or phone present | 20 |
| Business type present | 10 |
| Estimated store count > 0 | 10 |
| Estimated device count > 0 | 10 |
| Interest package code present | 15 |
| At least one completed activity | 10 |

A score of **≥ 60** flags the lead as ready for a manual qualification decision.

## Contact completeness

- [ ] Business name
- [ ] Contact name
- [ ] At least one of: contact email / contact phone

## Business profile completeness

- [ ] Business type
- [ ] Estimated store count
- [ ] Estimated device count

## Commercial intent

- [ ] Interest package code (see `saas-package-catalog.md`)
- [ ] Budget / pricing expectation captured as an activity note *(placeholder — no
      real quoting in Sprint 22)*

## Manual review requirement

- Qualification (`POST /sales-leads/{lead}/qualify`) is always a **manual** admin
  action. The advisory score informs, never replaces, the decision.

## Onboarding handover readiness

- Marking `ready-for-onboarding` (`POST /sales-leads/{lead}/ready-for-onboarding`)
  signals a **manual onboarding review**. It never creates a tenant, user,
  subscription, or device. See `onboarding-handover-readiness.md`.
