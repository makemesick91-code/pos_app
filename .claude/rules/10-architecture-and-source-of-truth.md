# 10 — Architecture & Source of Truth

How Aish POS is layered and where authoritative logic must live.

## Layering
- Routes/middleware → Controllers (or Blade controllers) → `App\Services\*` domain
  services → Eloquent models. Business decisions happen in services only.
- Blade views and API resources present data; they never decide business outcomes.

## Canonical services (reuse, never fork)
- Tenant status: `App\Services\*` — `TenantLifecycleService::resolve()` is the ONLY
  authoritative tenant status (suspended / trial / unpaid / active). Nothing else may
  recompute it.
- Billing invoices & payment collection: canonical Billing services.
- Entitlement / access decisions: canonical Entitlements service (central gate).
- Usage metering & limits: canonical usage-event ledger + limit services.
- Payment gateway / QRIS settlement: canonical PaymentGateway service (separate from the
  POS QRIS payment service).
- Onboarding / provisioning: canonical TenantOnboarding service.

## No duplicated business logic
- Do not re-derive suspension, trial expiry, entitlement, quota, invoice, or settlement
  state in a controller, request, resource, command, or Blade template. Call the service.
- If two surfaces need the same decision, both call the same service method. No copy-paste.

## Guard ordering (business API)
- Business routes run the chain: `tenant.lifecycle` → `tenant.entitled` → usage/limit,
  in that order, so suspension wins over entitlement and metering runs before limit checks.

## Web vs API parity
- The `/admin/*` browser console and `/api/v1/admin/*` API must agree because they call the
  same services. Never let the console read state the API cannot, or vice versa.

## Additive change discipline
- Prefer additive changes to services and migrations; do not silently repurpose an existing
  table/column that another sprint owns. New concern → new table/service, documented.
