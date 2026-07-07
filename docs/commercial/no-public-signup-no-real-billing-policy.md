# No Public Signup / No Real Billing Policy — Aish POS Lite

Sprint 20 — Commercial Launch Readiness & SaaS Packaging Foundation.

This policy is a hard boundary for Sprint 20 and is enforced by tests and the
commercial launch gate.

## Explicit prohibitions in Sprint 20

- **No public signup.** There is no public self-service registration endpoint.
  Onboarding is admin-driven (Sprint 12 foundation).
- **No real billing collection.** The app does not collect real money for the
  SaaS subscription. `monthly_price` in the package catalog is governance
  metadata only.
- **No subscription payment automation.** No payment gateway subscription is
  created or automated for SaaS billing. (QRIS from Sprint 5 remains a POS sale
  payment feature, not SaaS billing.)
- **No public marketing website / public pricing page.** Sales enablement is
  internal, admin-only.

## What remains authoritative

- `SubscriptionPlan` / `TenantSubscription` / `RegisteredDevice` (Sprint 10)
  remain the **runtime** subscription and device-limit enforcement source.
- Platform admin (Sprint 11) and tenant onboarding (Sprint 12) remain the
  admin-only governance surface.

## Admin-only commercial governance

All commercial launch, package, risk, and signoff APIs live under
`/api/v1/admin/*` behind `auth:sanctum` + `platform.admin`. Tenant users and
unauthenticated callers cannot access them.
