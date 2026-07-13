# Subscription, Billing & Invoice Console — Governance Foundation (UIX-5)

This document records the enforceable governance for the UIX-5 billing console.
It complements `docs/foundation/uix-5-subscription-billing-invoice-console.md`
(architecture) and `.claude/rules/35-subscription-billing-invoice-integrity.md`
(modular rule, UIX5-R001..UIX5-R028).

## Surface boundaries
- Two surfaces, never sharing authorization: Tenant Owner Billing Center
  (`/owner/billing/*`, `owner` guard) and Platform Admin Billing Operations
  (`/admin/billing/*`, `web` guard). An owner session cannot reach `/admin/billing`;
  a platform-admin session cannot reach `/owner/billing`; a Sanctum API bearer token
  authenticates neither browser surface.

## Financial source of truth
- The canonical billing domain (`App\Services\Billing\*`, `App\Services\PaymentGateway\*`)
  and the `tenant_billing_*` models own all financial truth. The console's
  `BillingConsoleReadService` is a read adapter: it never recomputes an invoice
  total, tax, discount, paid amount, outstanding balance, lifecycle status, or
  settlement outcome.

## Amount handling
- Whole-rupiah integers only. No PHP float is used for authoritative money, and no
  `/100` cents conversion exists. Display formatting flows exclusively through the
  `<x-rupiah>` Blade component, which also emits the truthful "Tidak tersedia"
  state for a null amount.

## Status semantics
- Invoice document status (`draft/issued/void/cancelled`), collection state
  (`not_due/pending/paid/failed/overdue/written_off/cancelled`), payment status
  (`pending/recorded/confirmed/failed/cancelled/refunded`), and QRIS intent status
  (`pending/requires_action/paid/expired/failed/cancelled`) are distinct
  vocabularies with distinct labels. "Lunas" is reserved for `collection_state =
  paid`; a paid gateway intent is never presented as a settled invoice.

## Tenant authorization & invoice-document security
- Every owner query is explicitly scoped to `OwnerContext->tenantId()`; foreign or
  unknown invoice ids return 404. Invoice documents are delivered only through the
  authenticated download route with a sanitised, canonical-number-derived filename,
  private/non-cacheable headers, and no public/direct-storage URL.

## Immutable history & read-only-first
- Invoice and payment history is append-only/immutable except through the owning
  service's governed correction/void path. The console performs no direct model
  mutation of billing state and exposes no mutation route. Any future mutation must
  satisfy UIX5-R017 in full before it ships.

## Audit, privacy & redaction
- Owner and admin invoice detail views and downloads are audited via
  `AdminAuditLogger` (sanitized: no password/token/secret/hash/PII). Rendered
  documents and logs never include signature or payload hashes, gateway secrets, or
  raw webhook payloads.

## Transport & deployment
- While HTTPS/domain is absent, serving real billing/invoice data over public
  plaintext HTTP is NO-GO; the console is reachable only via an encrypted operator/
  user channel. Deployments preserve PHP-FPM ownership of `storage/framework` and
  `bootstrap/cache`, and must not touch or regress DaengtisiaMS.

## Tests & release gates
- Cross-tenant isolation, surface separation, financial integrity, status
  transitions, and download security are mandatory release blockers, exercised by
  the `Uix5*` feature suite. GO requires the authoritative PR CI green, a real
  deploy + runtime verification, DMS non-regression, real evidence, local/origin/VPS
  exact match, an annotated GO tag on the release commit, and immutable prior tags.
