# Package / Pricing Content Alignment — Aish POS Lite

Sprint 21 — Public Website / Landing Page Readiness Foundation.

## Relationship to Sprint 20 SaaS package catalog

Public package/pricing content must be sourced from the **commercial package
catalog** (`saas_package_catalogs`, Sprint 20). The packages page (`/packages`)
renders only `ACTIVE` catalog packages. Landing `package_highlights` are validated
against active catalog `package_code`/`target_segment` by
`LandingPageContentService::packageAlignment()`; a mismatch is a WATCH signal.

## Relationship to SubscriptionPlan / TenantSubscription

Displayed prices are **governance metadata**, aligned with pricing-plan governance
(Sprint 20). The public site does **not** read or write `SubscriptionPlan` /
`TenantSubscription` and does **not** activate any subscription.

## Package / pricing claim checklist

- [ ] Every displayed package maps to an `ACTIVE` catalog package.
- [ ] Prices match pricing-plan governance (Sprint 20).
- [ ] No feature is promised beyond the approved package scope.
- [ ] Device limits shown match the catalog `device_limit`.
- [ ] No discount/billing promise beyond approved commercial docs.

## No real billing collection rule

`public_website.real_billing_collection_allowed = false`. The public site never
collects payment, never creates an invoice, and never automates subscription
billing. Activation is manual via the platform admin. See
[../commercial/no-public-signup-no-real-billing-policy.md](../commercial/no-public-signup-no-real-billing-policy.md).
