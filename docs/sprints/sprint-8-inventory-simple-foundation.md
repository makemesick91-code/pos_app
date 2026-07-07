# Sprint 8 — Inventory Simple Foundation

Canonical source of truth: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
(sections 8–12, 14, 16, 17, 21, 22, 25, 26).

## Objective

Establish a **simple, ledger-based** inventory foundation. Current stock is
always derived from `inventory_movements` (the signed sum of `signed_qty`);
there is **no mutable `current_stock` column** as source of truth. Successful
sales decrement stock through automatic `SALE_OUT` movements that are safe under
idempotent offline replay.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
- `docs/PROJECT_RULES.md` (Foundation Lock Index + Sprint 0–8 runtime rules)
- Sprint 0–7 evidence docs.

## Previous Sprint Foundation Lock

Sprint 0–7 runtime rules remain intact in `docs/PROJECT_RULES.md`. The Foundation
Lock Index now lists Sprint 8. `backend/config/pos_foundation.php` records the
Sprint 8 rule flags and the `sprint_8` entry.

## Scope

In scope: `inventory_movements` ledger, `InventoryMovement` model, movement
service, stock calculator, adjustment API, current-stock API, product-stock API,
movement listing API, automatic `SALE_OUT` from sales, idempotency safety, and
lightweight Android stock visibility.

Out of scope (deferred): procurement, purchase orders/goods receipt, stock
transfer, complex stock opname, batch/lot/expiry, multi-warehouse, advanced
valuation, large inventory reports, owner inventory dashboard, offline inventory
adjustment sync.

Movement types implemented: `OPENING`, `SALE_OUT`, `ADJUSTMENT_IN`,
`ADJUSTMENT_OUT`, `RETURN_IN` (RETURN_IN reserved; not yet issued at runtime).

## Graphify Summary

- Product foundation (Sprint 2) provides `is_stock_tracked` — the gate for
  `SALE_OUT`.
- Sale/SaleItem (Sprint 4) provides the immutable line snapshots that
  `SALE_OUT` references (`reference_type=sale_item`, `reference_id=sale_items.id`).
- Offline idempotency (Sprint 7) returns the existing sale before line
  persistence, so a replay never reaches `SALE_OUT` creation; a DB unique guard
  is the second line of defence.
- TenantContext (Sprint 1) supplies tenant/store scoping for every inventory
  read and write.

## Backend Implementation

- Migration: `database/migrations/2026_07_07_100000_create_inventory_movements_table.php`.
- Model: `app/Models/InventoryMovement.php` (+ reverse relations on Tenant,
  Store, Product, User).
- Services: `app/Services/Inventory/InventoryMovementService.php`,
  `app/Services/Inventory/StockCalculator.php`.
- Sale integration: `app/Services/SaleService.php` (`persistLines` now creates a
  `SALE_OUT` per stock-tracked line inside the sale transaction).
- Requests: `IndexCurrentStockRequest`, `IndexInventoryMovementRequest`,
  `StoreInventoryAdjustmentRequest`.
- Controllers: `InventoryCurrentStockController` (index + show),
  `InventoryMovementController`, `InventoryAdjustmentController`.
- Resources: `CurrentStockResource`, `InventoryMovementResource`.
- Routes: added under `/api/v1/inventory/*`.

## Inventory Movement Ledger

`inventory_movements` columns: `tenant_id`, `store_id`, `product_id`,
`movement_type`, `qty` (always positive), `signed_qty` (backend-computed sign),
`reference_type`, `reference_id`, `source`, `notes`, `created_by`, timestamps.
Indexes cover tenant/store/product access paths, `movement_type`, and
`(reference_type, reference_id)`. A composite unique index
`(tenant_id, store_id, product_id, movement_type, reference_type, reference_id)`
guards referenced movements (e.g. `SALE_OUT`) against duplication; NULL
references remain non-unique across SQLite/MySQL/PostgreSQL, so opening/manual
adjustments are unaffected.

## Stock Calculation Rules

- `current_stock = SUM(signed_qty)` for a (tenant, store, product).
- No movement → `0.00`.
- Always filtered by `tenant_id` **and** `store_id`; cross-tenant reads are
  impossible.
- Sign source of truth: `OPENING/ADJUSTMENT_IN/RETURN_IN` positive;
  `ADJUSTMENT_OUT/SALE_OUT` negative. Clients never send `signed_qty`.

## Sale Out Integration

On a successful CASH sale (online or accepted offline sync), each stock-tracked
sale item creates one `SALE_OUT` with negative `signed_qty = -qty`,
`source = SALE`, referencing the sale item. Non-stock-tracked products create no
movement. The movement is created inside the sale DB transaction, so a movement
failure rolls back the sale.

**QRIS clarification:** the current sale creation flow finalizes CASH sales as
PAID at creation time; `SALE_OUT` applies to those paid CASH sales and to synced
offline CASH sales. QRIS-pending stock deduction is intentionally **not**
implemented here and is left for a future sprint (would require an idempotent,
tested PAID transition). No QRIS behavior from Sprint 5 changed.

## Idempotency Rules

- A replayed offline sale (same `tenant/store/client_reference`) returns the
  existing sale and never re-runs line/`SALE_OUT` creation.
- The service also checks for an existing `SALE_OUT` per sale item before
  inserting, and the DB unique guard catches any concurrent race.
- The same `client_reference` under a different tenant stays isolated (its own
  sale and its own single `SALE_OUT`).

## Adjustment API

`POST /api/v1/inventory/adjustments` accepts `OPENING`, `ADJUSTMENT_IN`,
`ADJUSTMENT_OUT` only. `tenant_id` is taken from context; `store_id`/`product_id`
are validated to belong to the tenant; `qty > 0`; `signed_qty` is backend
computed. `SALE_OUT` is rejected here (422).

## Current Stock API

- `GET /api/v1/inventory/current-stock` — lightweight, bounded (`limit` ≤ 200),
  tenant/store isolated list of products with derived stock.
- `GET /api/v1/inventory/products/{product}/stock` — single product stock;
  404 when the product is not the tenant's.
- `GET /api/v1/inventory/movements` — basic tenant-isolated movement listing.

## Tenant Isolation Rules

Every inventory query filters on the authenticated `tenant_id`. Product/store
inputs are validated against the tenant. Cross-tenant reads (stock, movements)
return empty/404; cross-tenant writes fail validation.

## Android Implementation

- DTOs: `data/remote/dto/StockDtos.kt`.
- API: `PosApiService` gains `getCurrentStock` and `getProductStock`.
- Repository: `data/repository/StockRepository.kt` (read-only; no local
  authoritative stock).
- Pure display helper: `feature/cashier/StockDisplay.kt`.
- Wiring: `ServiceLocator.stockRepository`.

## Android Stock Visibility

The cashier product list shows a lightweight stock label per row: `Stok: <n>`,
`Stok: -` when unknown, and a warning colour when stock ≤ 0. The label is
informational only — it never blocks a sale, and the backend remains
authoritative. Stock refreshes on screen open, after catalog sync, and after a
successful checkout. No dashboards or heavy reports.

## Android Build CI Evidence

`.github/workflows/sprint8-ci.yml` runs three jobs: `foundation-and-smoke`,
`backend-tests` (PHP 8.5, `composer validate --strict` + `php artisan test`),
and `android-build-test` (JDK 21, `assembleDebug` + `testDebugUnitTest`). Android
build/test is required (not `continue-on-error`).

## Application Rules Update

`docs/PROJECT_RULES.md` gains the **Sprint 8 Inventory Simple Foundation Runtime
Rule** and lists Sprint 8 in the Foundation Lock Index. Sprint 0–7 rules are
unchanged. `pos_foundation.php` adds `inventory_ledger_only`,
`sale_out_movement_required`, `inventory_current_stock_from_movements`,
`inventory_idempotency_safe`, and `sprint_8`.

## Testing Evidence

Backend feature tests (all green with the full suite — 156 tests):

- `InventoryAdjustmentApiTest` — opening/in/out, signed_qty, positive qty,
  SALE_OUT rejected, product/store ownership.
- `CurrentStockApiTest` — sum of signed_qty, zero default, store scope,
  tenant-only listing, non-stock-tracked behavior.
- `InventorySaleOutTest` — paid cash + offline sync create SALE_OUT, negative
  signed_qty, snapshot preserved, non-stock-tracked skipped.
- `InventoryTenantIsolationTest` — no cross-tenant stock/movements/adjustments.
- `InventoryIdempotencyTest` — replay creates no duplicate sale/SALE_OUT; same
  reference in another tenant isolated.

Android unit tests: `StockDtoMappingTest` (display mapping),
`StockRepositoryTest` (backend abstraction success/error, no local mutation).

## Backend Compatibility Evidence

No previous routes changed. `SaleService` gained a constructor dependency
(`InventoryMovementService`, auto-resolved by the container). Sale/receipt/QRIS/
payment/webhook/sync/auth endpoints and behavior are unchanged. Full suite passes.

## Validation Commands

```bash
bash scripts/sprint8_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- Sprint 8 smoke: PASS (local).
- Backend `php artisan test`: PASS — 156 tests, 532 assertions.
- Android build/test: gated on `sprint8-ci` (JDK 21); local Android build is not
  possible on this machine (JDK 25, no local Gradle), so CI is the build gate.

## GO Criteria

See the task GO criteria (1–28). Key gates: ledger-only stock, no mutable
source-of-truth column, SALE_OUT for paid cash + offline sync, idempotent replay
safety, tenant isolation, all endpoints present, Android stock visibility, Sprint
8 rules locked, smoke + backend tests pass, Android CI assembleDebug +
testDebugUnitTest green, no forbidden files, GO tag on main HEAD.

## No-Go Checks

Stock from mutable column, client-set `signed_qty`, manual `SALE_OUT`, missing
`SALE_OUT` for stock-tracked paid sale, duplicate SALE_OUT on replay, cross-tenant
access, broken previous-sprint behavior, Android CI not running assembleDebug/
testDebugUnitTest — all verified absent.

## Follow-up for Sprint 9

Sprint 9 — Reports & Closing Foundation (sales/stock reporting, shift close),
plus a future decision on QRIS-PAID stock deduction and returns (`RETURN_IN`).
