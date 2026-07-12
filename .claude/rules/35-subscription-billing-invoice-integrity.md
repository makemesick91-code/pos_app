# 35 — Subscription, Billing & Invoice Console Integrity (UIX-5)

The `/owner/billing/*` (Tenant Owner Billing Center) and `/admin/billing/*`
(Platform Admin Billing Operations) surfaces present subscription, invoice,
payment, QRIS, and settlement state. They are read-only presentation over the
canonical billing domain — never a second billing engine.

## Source of truth & no duplication
- UIX5-R001 — Subscription, entitlement, usage, billing, invoice, renewal,
  dunning, QRIS, and settlement services remain canonical. The console reuses
  `App\Services\Billing\*` (Sprint 30), `App\Services\PaymentGateway\*`
  (Sprint 31), `TenantPlanResolver`, `TenantLifecycleService`, and the
  entitlement/usage services.
- UIX5-R002 — Controllers, view models, and Blade templates must not duplicate
  financial business logic. `App\Services\BillingConsole\BillingConsoleReadService`
  is a read adapter: it reads canonical columns and calls canonical methods
  (`TenantBillingInvoice::collectedAmount()/outstandingAmount()/isPaid()`,
  `BillingSummaryService::invoiceSummary()/collectionSummary()`), never
  recomputing.

## Authorization & tenancy
- UIX5-R003 — Tenant Owner billing access is always tenant-scoped and
  deny-by-default; the tenant comes from `OwnerContext` (server-resolved), never
  from request input.
- UIX5-R004 — Platform Admin billing access requires the `platform.admin.web`
  gate and never grants owner membership.
- UIX5-R005 — Tenant Owner access never grants platform-global visibility.
- UIX5-R006 — Invoice resolution and download authorization enforce the active
  surface and tenant boundary; a foreign/unknown invoice id returns 404. Owner
  invoices are never resolved by implicit route-model binding.
- UIX5-R007 — Public or unauthenticated invoice URLs are forbidden; there is no
  direct-storage URL for an invoice document.

## Financial integrity
- UIX5-R008 — Financial values use canonical money types (whole-rupiah integers)
  and must not use unsafe floating-point arithmetic or a `/100` cents conversion.
- UIX5-R009 — Currency, rounding, tax, discount, billing-period, and timezone
  semantics come from the existing domain source of truth.
- UIX5-R010 — Invoice totals and balances are displayed from canonical persisted
  or domain-calculated values, never recomputed in views. Money is formatted only
  through the `<x-rupiah>` component.
- UIX5-R011 — Invoice issued, payment pending, paid, settled, failed, expired,
  refunded, and void states must remain semantically distinct.
- UIX5-R012 — QRIS payment state is never presented as settlement unless the
  canonical settlement source confirms it (a created/paid gateway intent is not a
  settled invoice; only `collection_state = paid` renders "Lunas").
- UIX5-R013 — Unknown or unsupported values render as "Tidak tersedia", never a
  fabricated zero.
- UIX5-R014 — Historical invoice and payment evidence is immutable except through
  explicit governed correction/void mechanisms in the owning service.

## Read-only-first & mutation discipline
- UIX5-R015 — Direct model updates for subscription, invoice, payment, dunning,
  and settlement states are forbidden in UI controllers.
- UIX5-R016 — Billing console scope is read-only first unless a governed,
  idempotent, audited mutation service already exists. UIX-5 ships read-only.
- UIX5-R017 — Any future financial mutation requires authorization, confirmation,
  idempotency, audit, tests, and documented compensation behaviour.

## Documents, privacy & performance
- UIX5-R018 — Invoice documents are generated/retrieved through authenticated,
  authorized, non-path-traversable delivery (filename derived from the canonical
  invoice number, never request input).
- UIX5-R019 — Invoice documents and logs redact credentials, tokens, webhook
  secrets, signature/payload hashes, and unnecessary PII; reads reuse
  `AdminAuditLogger` sanitization.
- UIX5-R020 — Billing responses are private/non-cacheable; cache keys (where used)
  include surface, identity, tenant, period, and filter scope.
- UIX5-R021 — Subscription and invoice lists are paginated and bounded.
- UIX5-R022 — Search and sort fields are explicitly whitelisted.

## Release gates
- UIX5-R023 — Cross-tenant invoice list, detail, export, and download tests are
  mandatory release blockers.
- UIX5-R024 — Financial-integrity and status-transition regression tests are
  mandatory release blockers.
- UIX5-R025 — Public plaintext HTTP access involving real billing or invoice data
  remains NO-GO.
- UIX5-R026 — Production Artisan cache operations must preserve PHP-FPM runtime
  ownership of `storage/framework` and `bootstrap/cache`.
- UIX5-R027 — Shared-VPS deployment must not change or regress DaengtisiaMS.
- UIX5-R028 — GO requires observed evidence, authoritative CI success,
  local/origin/VPS exact match, runtime verification, and immutable previous tags.
