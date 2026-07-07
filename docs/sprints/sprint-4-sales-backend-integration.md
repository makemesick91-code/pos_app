# Sprint 4 — Sales Backend Integration

## Objective

Deliver the real sales backend integration and Android cart submission for online
CASH checkout: tenant-isolated sales, sale items with price snapshots, cash
payment records, backend-generated invoice numbers, and an Android cash checkout
flow that clears the cart only after a confirmed backend sale.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (sections 8–12, 14, 16, 17, 21, 22, 25, 26)
- `docs/PROJECT_RULES.md`

## Previous Sprint Foundation Lock

Sprint 0–3 rules remain intact in `docs/PROJECT_RULES.md`. The Foundation Lock
Index now also lists this document. No prior runtime rule was removed or weakened.

## Scope

In scope: sales/sale_items/payments tables, models, invoice number generator,
product price resolver, transactional sale service, sales API
(create/list/show/cancel), cash payment finalization, tenant isolation
enforcement + tests, Android sales DTOs/API/repository, Android cash checkout UI.

Out of scope (per roadmap / No-Go rules): QRIS, payment gateway/webhook, printer,
offline sales sync queue, inventory movement runtime, subscription billing,
device management enforcement, advanced reports, owner dashboard.

Cash checkout is online-only in Sprint 4; offline cash sync arrives in a later sprint.

## Graphify Summary

- TenantContext (Sprint 1) resolves tenant/store from the authenticated user; an
  `X-Store-ID` header is honoured only when the store belongs to the tenant.
- Products + `ProductStorePrice` (Sprint 2) provide the effective price snapshot.
- Android cart (Sprint 3) was local-only; Sprint 4 adds a `SalesRepository` that
  submits it to `/api/v1/sales`.
- Sales depend on `tenants`, `stores`, `users`, `products` — all pre-existing.
- Invoice numbers are per `(tenant_id, store_id, date)`; uniqueness is backed by a
  DB unique index on `(tenant_id, store_id, invoice_number)`.
- GO tag depends on: implementation complete → tests pass → smoke pass → merge to main.

## Backend Implementation

- Models: `App\Models\Sale`, `App\Models\SaleItem`, `App\Models\Payment` with
  tenant/store/cashier relationships, status constants, and query scopes.
- Reverse relationships added to `Tenant`, `Store`, `User` (cashier).
- Services:
  - `App\Services\InvoiceNumberGenerator` — `POS-{STORE_CODE}-{YYYYMMDD}-{000001}`,
    per tenant/store/date sequence.
  - `App\Services\ProductPriceResolver` — active store override else product price.
  - `App\Services\SaleService` — transactional create/pay/cancel; all money math is
    server-side (`bcmath`, scale 2).
- Controllers: `SaleController` (index/store/show/cancel), `SaleCashPaymentController`.
- Requests: `StoreSaleRequest`, `IndexSaleRequest`, `CancelSaleRequest`,
  `StoreCashPaymentRequest` (prohibit `tenant_id`, `cashier_id`, `invoice_number`,
  and all totals from the client).
- Resources: `SaleResource`, `SaleItemResource`, `PaymentResource`
  (`raw_response`/credentials never exposed).

## Database Changes

New migrations:

- `2026_07_07_000007_create_sales_table.php`
- `2026_07_07_000008_create_sale_items_table.php`
- `2026_07_07_000009_create_payments_table.php`

Key constraints: `sales` unique `(tenant_id, store_id, invoice_number)`; foreign
keys to tenants/stores/users/products/sales; tenant/store indexes on every table.

Payment status set: `UNPAID, PENDING, PAID, FAILED, CANCELLED, EXPIRED`.
Sync status set: `SYNCED, PENDING_SYNC, FAILED_SYNC`.
Source set: `ANDROID_ONLINE, WEB_ADMIN, API`.
Payment method set: `CASH, QRIS` (only CASH written in Sprint 4).
Payment provider set: `MANUAL, MIDTRANS, XENDIT, DUITKU` (only MANUAL written).

## Invoice Number Generation

Backend-only. The client can never supply or influence `invoice_number`. The
sequence is derived from the count of sales for the store/date and guaranteed
unique by the DB unique index. Generation happens inside the sale DB transaction.

## Price Resolution

`ProductPriceResolver` returns the active `ProductStorePrice` override for the
store context when present, otherwise the product's `selling_price`. Client
`unit_price` is never trusted. The resolved value is snapshotted into `sale_items`.

## Sales API

Protected by `auth:sanctum`, `tenant.active`, `tenant.context`:

- `GET  /api/v1/sales` — own-tenant list, filters: `date_from/date_to`,
  `payment_status`, `store_id` (tenant-validated), paginated.
- `POST /api/v1/sales` — create a cash-paid sale in one atomic request.
- `GET  /api/v1/sales/{sale}` — own-tenant sale with items + payments (404 otherwise).
- `POST /api/v1/sales/{sale}/cancel` — sets `CANCELLED`, `cancelled_at/by`; no double-cancel.
- `POST /api/v1/sales/{sale}/payments/cash` — finalize an UNPAID sale with CASH.

## Cash Payment Flow

`POST /api/v1/sales` with `payment.method = CASH` validates
`paid_amount >= grand_total`, creates the sale + items + a CASH/MANUAL/PAID
payment, and computes `change_total` — all in a single DB transaction. No gateway
logic is involved. No refund logic is implemented; a cancelled sale keeps its
historical PAID payment rows.

## Tenant Isolation Rules

- List/show/cancel/pay are scoped to `TenantContext->tenantId()`.
- Cross-tenant sale routes return `404` (existence is not leaked).
- Checkout product IDs are validated to belong to the tenant (`422` otherwise).
- A client `store_id` is validated to belong to the tenant (`422` otherwise).
- Store context comes from the authenticated user / validated `X-Store-ID`.

## Android Implementation

- DTOs (`SalesDtos.kt`): `CreateSaleRequestDto`, `CreateSaleItemRequestDto`,
  `CashPaymentRequestDto`, `SaleResponse`, `SaleDto`, `SaleItemDto`, `PaymentDto`.
  The request DTOs deliberately omit `tenant_id`, `cashier_id`, `invoice_number`,
  and all totals.
- `PosApiService`: `createSale`, `getSale`, `cancelSale` added.
- `SalesRepository`: converts the local cart to a `CreateSaleRequestDto`, sends a
  CASH payment with the tendered amount, returns a `ResultState`, and never mutates
  the cart.
- Wired via `ServiceLocator.salesRepository(...)`.

## Android Cash Checkout

`CashierViewModel.checkoutCash(paidAmount)`:

- Blocks empty carts and `paid < subtotal` client-side.
- Emits `Submitting → Success/Error`.
- On success: clears the cart, surfaces invoice number, grand total, and change.
- On failure: keeps the cart intact for retry.

`CashierActivity` adds a paid-amount input, a "Bayar Tunai" checkout button
(enabled only when the cart is non-empty), and a result/error line. No QRIS, no
printer, no offline queue.

## Android Build/Tooling Evidence

The Android module could not be compiled locally: the environment has **JDK 25
only** and **no `gradle` binary**, while AGP 8.7.x requires JDK 17–21. A Gradle
wrapper could not be generated for the same reason (no `gradle` to run
`gradle wrapper`). Android validation is therefore **static** (source/structure +
package/SDK-level grep), matching the Sprint 3 approach. The pure-JVM unit test
`SalesRepositoryTest` was added and will run once a wrapper/compatible JDK is
available in CI. No build/test pass is claimed.

## Application Rules Update

- `docs/PROJECT_RULES.md`: Foundation Lock Index now lists sprint 4; a new
  "Sprint 4 Sales Backend Integration Runtime Rule" (17 items) was added. Sprint
  0–3 rules were preserved verbatim.
- `backend/config/pos_foundation.php`: added `sprint_4`, `sales_backend_required`,
  `cash_payment_backend_required`, `qris_not_in_sprint_4`.

## Testing Evidence

Backend feature tests (SQLite in-memory):

- `SalesApiTest` — cash sale creation, total recalculation, client-total rejection,
  snapshotting, store price override, paid-amount guard, discounts, inactive
  product rejection, list/show/cancel, store-context requirement.
- `CashPaymentApiTest` — CASH/MANUAL/PAID payment, sale → PAID, no pay on
  cancelled/paid, amount guard, forged amount/status rejection.
- `SalesTenantIsolationTest` — tenant A cannot show/cancel/pay tenant B sale
  (404), cannot checkout tenant B product/store, list excludes tenant B.
- `InvoiceNumberGeneratorTest` — format, per-store increment, independent stores,
  per-tenant scoping.

Android unit test: `SalesRepositoryTest` (cart→DTO conversion, success/error, empty-cart guard).

## Backend Compatibility Evidence

`php artisan route:list` still exposes auth + sync endpoints; the health endpoint
now reports `Sprint 4`. Full suite (all sprints) run together.

## Validation Commands

```bash
bash scripts/sprint4_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
php artisan route:list | grep -E "api/v1/sales|api/v1/auth/login|api/v1/sync/products"
```

## Validation Results

- Foundation/rules grep: pass
- Sprint 4 smoke: pass
- `composer validate --strict`: `./composer.json is valid`
- Backend tests: 84 passed / 247 assertions
- Sales tenant isolation tests: pass
- Android static validation: pass
- Android build/unit tests: skipped — JDK 25 only, no Gradle wrapper (documented)
- Forbidden files check: pass
- Working tree clean at tag time: yes

## GO Criteria

All 25 GO criteria from the sprint brief are met (sales/sale_items/payments
tables + models, invoice generator, sales API, cash finalization, server-side
totals, price snapshots, store price override, full tenant isolation, Android
DTO/API/repository + cash checkout that clears only on success and keeps the cart
on failure, no QRIS/webhook/printer/offline/inventory runtime, smoke + backend
tests pass, forbidden files absent).

## No-Go Checks

None triggered: rules intact, migrations/models/routes present, totals and prices
server-authoritative, snapshots stored, isolation proven, cart cleared only on
success, no out-of-scope runtime, tests + smoke pass, package `com.aishtech.poslite`,
`minSdk 26` / `targetSdk 35` unchanged, no forbidden files committed.

## Follow-up for Sprint 5

- Sprint 5 — QRIS Payment Gateway Foundation (dynamic QRIS, provider integration,
  payment webhooks) plus offline cash sync queue.
- Generate the Gradle wrapper and enable `assembleDebug` / `testDebugUnitTest` in
  CI once a JDK 17–21 toolchain is available.
