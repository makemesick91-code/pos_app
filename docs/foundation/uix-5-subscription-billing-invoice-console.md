# UIX-5 — Subscription, Billing & Invoice Console (Foundation)

UIX-5 adds a read-only Subscription/Billing/Invoice console to the two
authenticated browser surfaces delivered by UIX-3 and UIX-4:

- **Tenant Owner Billing Center** — `/owner/billing/*`, on the `owner` session
  guard (`tenant.owner.web`), tenant-scoped to the authenticated owner's own
  tenant.
- **Platform Admin Billing Operations** — `/admin/billing/*` and
  `/admin/tenants/{tenant}/billing`, on the `web` session guard
  (`platform.admin.web`), platform-scoped by design.

It is a **presentation layer over the canonical billing domain**, not a second
billing engine. All truth comes from existing services.

## Architecture

```
Route (guarded)
 → OwnerBillingController / AdminBillingController   (thin; resolve context, audit)
 → BillingConsoleReadService                         (read-only adapter)
     → BillingSummaryService (Sprint 30)             invoice/collection aggregates
     → PaymentGatewaySummaryService (Sprint 31)      QRIS intent/settlement aggregates
     → TenantBillingInvoice::collectedAmount()/outstandingAmount()/isPaid()
     → TenantPlanResolver / TenantLifecycleService   plan + authoritative status
 → Blade views + <x-rupiah> + status-badge partial   (present only)
```

### Canonical reuse (no duplication)
- Money is a **whole-rupiah `unsignedBigInteger`** (cast `integer`) throughout the
  `tenant_billing_*` tables. There are no minor units and no floats. The console
  reads these columns and the model's computed methods; it never re-sums payments,
  re-derives totals, or recomputes lifecycle/plan/settlement state.
- Outstanding/collected come from `BillingSummaryService::collectionSummary()`
  (which itself calls the invoice model's authoritative methods) so a single,
  consistent definition is presented everywhere.
- The `saas_billing_*` (Sprint 23/24) platform-governance tables are deliberately
  NOT mixed into these tenant-scoped views.

### Truthful state
- `<x-rupiah>` is the single money formatter; a null amount renders **"Tidak
  tersedia"**, never "Rp 0". Genuine zeros (no invoices yet) stay real zeros.
- Status badges are labelled (not colour-only). Invoice document lifecycle,
  payment status, QRIS intent status, and gateway event status use distinct
  vocabularies. A gateway intent that reached `paid` is shown as "Dibayar
  (gateway)"; only an invoice whose `collection_state = paid` shows "Lunas".

### Invoice documents
- Delivered as authenticated, print-ready HTML (no PDF generator exists in this
  build; none is added). No public/direct-storage URL. The download filename is
  derived from the sanitised canonical invoice number, so no request input reaches
  a filesystem path. Responses are `no-store, private` with `X-Content-Type-Options:
  nosniff`.

## Rule set (UIX5-R001..UIX5-R028)

- `UIX5-R001` — Canonical subscription/billing/invoice/QRIS/settlement services are reused, never forked.
- `UIX5-R002` — No duplicated financial business logic in controllers/view models/Blade; `BillingConsoleReadService` reads canonical columns/methods only.
- `UIX5-R003` — Owner billing is tenant-scoped, deny-by-default, from server-resolved `OwnerContext`.
- `UIX5-R004` — Admin billing requires `platform.admin.web` and never grants owner membership.
- `UIX5-R005` — Owner access never grants platform-global visibility.
- `UIX5-R006` — Invoice resolution/download enforce surface + tenant boundary; foreign/unknown id → 404; no implicit route-model binding for owner invoices.
- `UIX5-R007` — No public/unauthenticated invoice URL.
- `UIX5-R008` — Whole-rupiah integer money; no float arithmetic, no `/100` cents.
- `UIX5-R009` — Currency/rounding/tax/discount/period/timezone semantics from the domain source of truth.
- `UIX5-R010` — Totals/balances displayed from canonical values; formatting only via `<x-rupiah>`.
- `UIX5-R011` — Issued/pending/paid/settled/failed/expired/refunded/void stay semantically distinct.
- `UIX5-R012` — QRIS state is never shown as settlement unless the canonical source confirms it.
- `UIX5-R013` — Unknown values render "Tidak tersedia", never a fabricated zero.
- `UIX5-R014` — Historical invoice/payment evidence is immutable except via governed correction/void.
- `UIX5-R015` — No direct model mutation of billing state in UI controllers.
- `UIX5-R016` — Read-only first; UIX-5 ships read-only.
- `UIX5-R017` — Any future mutation needs authorization, confirmation, idempotency, audit, tests, compensation.
- `UIX5-R018` — Authenticated, authorized, non-path-traversable invoice document delivery.
- `UIX5-R019` — Documents/logs redact credentials/tokens/webhook secrets/hashes/PII.
- `UIX5-R020` — Private/non-cacheable billing responses; scoped cache keys.
- `UIX5-R021` — Paginated, bounded lists.
- `UIX5-R022` — Whitelisted search/sort fields.
- `UIX5-R023` — Cross-tenant list/detail/export/download tests are release blockers.
- `UIX5-R024` — Financial-integrity and status-transition tests are release blockers.
- `UIX5-R025` — Public plaintext HTTP with real billing data is NO-GO.
- `UIX5-R026` — Production cache ops preserve PHP-FPM ownership of `storage/framework` + `bootstrap/cache`.
- `UIX5-R027` — Shared-VPS deploy must not regress DaengtisiaMS.
- `UIX5-R028` — GO requires observed evidence, CI success, local/origin/VPS exact match, runtime verification, immutable prior tags.

## Verification
- Feature tests: `Uix5OwnerBillingTest`, `Uix5AdminBillingTest`,
  `Uix5BillingSurfaceSeparationTest`, `Uix5BillingFinancialIntegrityTest`,
  `Uix5InvoiceDownloadSecurityTest` (run via `--filter=Uix5`).
- Gates: `scripts/uix5_design_gate.sh` (chains UIX-4→1),
  `scripts/verify_application_foundation_rules.sh` (UIX-5 block), `uix5-ci.yml`.
