# Pricing & Plan Governance — Aish POS Lite

Sprint 20. Governs how commercial package pricing aligns with the existing
subscription foundation. Evaluated by `PricingPlanGovernanceService` and
`commercial:launch-readiness`.

> **No real billing collection rule.** Pricing here is governance metadata only.
> Nothing in Sprint 20 charges a real customer, creates a payment gateway
> subscription, or mutates `TenantSubscription`.

## Runtime enforcement stays where it is

`SubscriptionPlan`, `TenantSubscription`, and `RegisteredDevice` (Sprint 10)
remain the **runtime** subscription and device-limit enforcement source. The
package catalog is a commercial packaging layer on top; it must stay consistent
with, and never bypass, those records.

## Pricing matrix template

| Package | Segment | Monthly price | Currency | Device limit | Setup / onboarding notes |
| --- | --- | --- | --- | --- | --- |
| PKG-WARUNG-LITE | WARUNG | 49000 | IDR | 1 | Self-guided; no setup fee |
| PKG-UMKM-STARTER | GENERAL_UMKM | 99000 | IDR | 2 | Assisted onboarding |
| PKG-RETAIL-PRO | RETAIL | 199000 | IDR | 3 | Managed onboarding |

## Governance rules

1. Every ACTIVE package must carry `monthly_price`, `currency`, and `device_limit`.
2. When subscription plans exist, at least one ACTIVE package device limit should
   align with a `SubscriptionPlan.max_devices` value (else WATCH — governance
   drift).
3. Pricing changes are metadata edits; they never trigger billing.

## Decision

- **NO-GO** — no active package, or an active package missing pricing metadata.
- **WATCH** — plans exist but no active package aligns with a plan device limit.
- **GO** — active packages complete and (when plans exist) at least one aligned.
