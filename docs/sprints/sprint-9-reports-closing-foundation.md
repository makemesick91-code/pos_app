# Sprint 9 — Reports & Closing Foundation

## Objective

Establish a simple, backend-authoritative reporting and closing foundation:

```
Sales / Payments / Inventory Movements → Daily Summary → Closing Snapshot → CSV Export → Android Lightweight Summary
```

No advanced BI dashboard, accounting journal, PDF/Excel export, tax reporting,
stock valuation, or procurement reports are introduced.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (sections 8, 9, 10, 11, 12, 14, 15, 16, 17, 18, 21, 22, 25, 26)
- `docs/PROJECT_RULES.md`
- Sprint 0–8 evidence docs

## Previous Sprint Foundation Lock

Sprint 9 builds on and must not break:

- Sprint 1 multi-tenant `TenantContext` isolation
- Sprint 2 product catalog
- Sprint 4 `Sale` / `SaleItem` / `Payment` (CASH, PAID)
- Sprint 5 QRIS payment gateway (PENDING → PAID/FAILED/EXPIRED/CANCELLED)
- Sprint 6 receipt foundation
- Sprint 7 offline cash sync (synced sales become backend rows)
- Sprint 8 `inventory_movements` ledger (`signed_qty`)

## Scope

Included: daily sales summary, payment method summary, inventory movement
summary, daily closing snapshot with lock/duplicate protection, tenant-isolated
CSV export, Android lightweight reports screen + closing action.

Excluded: advanced owner dashboard, accounting/P&L, tax reporting complex,
procurement, stock valuation, batch/lot/expiry, stock opname closing, multi-shift
complex close, reopen approval workflow, PDF/Excel export, heavy charts, BI
dashboard.

## Graphify Summary

- Reports read authoritative rows from `sales`, `payments`, `inventory_movements`,
  always scoped by `TenantContext` (never client-provided tenant).
- Only PAID sales count as revenue; CANCELLED counted separately; pending QRIS
  excluded from paid revenue; offline cash counts only after it exists in the
  backend (post-sync).
- Inventory summary is derived from the `inventory_movements` ledger's
  backend-computed `signed_qty` (never a mutable stock column).
- Daily closing composes the three report services into a snapshot and persists
  it once per (tenant, store, business_date); duplicate close replays the row.
- CSV export reuses the same summary/filters; it never emits secrets or raw
  gateway payloads.
- Android displays backend-computed values only and calls the closing endpoint;
  it never recomputes authoritative totals.

## Backend Implementation

- Migration `2026_07_07_200000_create_daily_closings_table.php`
- Model `App\Models\DailyClosing` (scopes `forTenant`/`forStore`/`forBusinessDate`/`closed`, `STATUS_CLOSED`)
- Factory `Database\Factories\DailyClosingFactory`
- Services under `app/Services/Reports/`:
  - `DailySalesReportService`
  - `PaymentSummaryReportService`
  - `InventoryMovementSummaryService`
  - `DailyClosingService`
  - `CsvReportExporter`
- Requests: `ReportDateFilterRequest`, `StoreDailyClosingRequest`, `IndexDailyClosingRequest`
- Controllers: `Reports\DailySalesReportController`, `Reports\PaymentSummaryReportController`,
  `Reports\InventoryMovementSummaryController`, `Reports\DailySalesCsvExportController`,
  `DailyClosingController`
- Resources: `DailySalesReportResource`, `PaymentSummaryResource`,
  `InventoryMovementSummaryResource`, `DailyClosingResource`
- Config `config/pos_foundation.php` updated with Sprint 9 + rule flags

### Routes (all under `auth:sanctum` + `tenant.active` + `tenant.context`)

```
GET  /api/v1/reports/daily-sales
GET  /api/v1/reports/daily-sales/export.csv
GET  /api/v1/reports/payment-summary
GET  /api/v1/reports/inventory-movements-summary
POST /api/v1/closings/daily
GET  /api/v1/closings/daily
GET  /api/v1/closings/daily/{dailyClosing}
```

## Daily Sales Report

`GET /api/v1/reports/daily-sales`. Filters: `store_id`, `date` or
`date_from`/`date_to`, `cashier_id` (all optional, tenant-scoped). When no date
is provided it defaults to **today**. Only PAID sales are counted as revenue;
cancelled sales are counted in `cancelled_sales_count`. Output includes
`sales_count`, `gross_total`, `discount_total`, `tax_total`, `grand_total`,
`paid_total`, `change_total`, `average_sale`, `cash_sales_count`,
`qris_sales_count`.

## Payment Summary Report

`GET /api/v1/reports/payment-summary`. Groups payments by `(method, status)` and
returns `count` + `amount_total`. Only PAID rows are realized revenue; PENDING /
FAILED / EXPIRED / CANCELLED are reported as their own rows and never mixed into
a paid total. Filtered by the owning sale's business date so it aligns with the
daily sales summary.

## Inventory Movement Summary

`GET /api/v1/reports/inventory-movements-summary`. Groups
`inventory_movements` by `movement_type` and returns `movement_count`,
`qty_total`, and `signed_qty_total` (from the backend-computed `signed_qty`). No
valuation.

## Daily Closing Snapshot

`POST /api/v1/closings/daily` with `{ store_id, business_date, notes? }`.
`tenant_id` comes from context, `closed_by` from the authenticated user, and all
totals are computed by the report services. The snapshot JSON stores the full
ringkasan (daily sales + payment summary + inventory summary) at close time.

## Closing Lock Rules

- Composite unique index `(tenant_id, store_id, business_date)`.
- A duplicate close returns the existing closing with `meta.duplicate_replay=true`
  and HTTP 200 (a fresh close returns HTTP 201). No duplicate row is created.
- A lost unique-index race is recovered by replaying the existing row.
- `business_date` may not be in the future (`before_or_equal:today`).
- Client-provided totals are never in the ruleset and are ignored.
- No reopen workflow in Sprint 9.

## CSV Export

`GET /api/v1/reports/daily-sales/export.csv`. Same filters and figures as the
JSON daily sales endpoint. `Content-Type: text/csv`; includes a header row; is
tenant-isolated; never emits `raw_response`/gateway secrets.

## Tenant Isolation Rules

Every report/closing query is scoped by `TenantContext::tenantId()`. `store_id`
and `cashier_id` inputs must belong to the tenant (validated via
`Rule::exists(...)->where('tenant_id', ...)`). `closings/daily/{id}` returns 404
for another tenant's closing. CSV export excludes other tenants' rows.

## Android Implementation

- DTOs: `data/remote/dto/ReportDtos.kt`, `data/remote/dto/ClosingDtos.kt`
- `PosApiService` gains report + closing endpoints
- Repositories: `data/repository/ReportRepository.kt`, `data/repository/ClosingRepository.kt`
- Pure-JVM mapping: `feature/reports/ReportDisplay.kt`
- Screen: `feature/reports/ReportsActivity.kt`, `ReportsViewModel.kt`, `res/layout/activity_reports.xml`
- Navigation: "Ringkasan" button in `CashierActivity`
- `ServiceLocator` wires `reportRepository` / `closingRepository`
- Manifest registers `ReportsActivity`

## Android Reports Screen

"Ringkasan Harian" shows business date, refresh button, PAID sales count,
cancelled count, cash total, QRIS total, grand total, SALE_OUT qty, closing hint,
and a "Tutup Hari Ini" button. It displays backend-computed values only; it never
computes authoritative totals, and it degrades gracefully for null/empty values.

## Android Closing Action

The "Tutup Hari Ini" button calls `POST /closings/daily`. A duplicate/already
closed response maps to a user-friendly "sudah ditutup sebelumnya" message via
`ReportDisplay.closingMessage(duplicateReplay)`.

## Android Build CI Evidence

`.github/workflows/sprint9-ci.yml` runs `android-build-test` on JDK 21:
`./gradlew :app:assembleDebug` + `./gradlew :app:testDebugUnitTest`. Not optional,
no `continue-on-error`. CI is the Android build authority (JDK 25 locally cannot
run the Gradle 8.11 wrapper — see Sprint 6 evidence).

## Application Rules Update

`docs/PROJECT_RULES.md`: Foundation Lock Index now lists
`sprint-9-reports-closing-foundation.md`, and the **Sprint 9 Reports & Closing
Foundation Runtime Rule** (19 mandatory rules) was added. All Sprint 0–8 rules
remain intact.

## Testing Evidence

Backend feature tests (`backend/tests/Feature/`):

- `DailySalesReportApiTest` — PAID cash/QRIS counted; pending QRIS excluded;
  cancelled counted separately; offline-synced sale counted; date/store/cashier
  filters.
- `PaymentSummaryReportApiTest` — CASH/QRIS PAID totals; pending/failed not mixed
  into paid; store scope.
- `InventoryMovementSummaryReportApiTest` — SALE_OUT/ADJUSTMENT summarized; signed
  totals; store scope.
- `DailyClosingApiTest` — backend-calculated totals; client totals ignored;
  duplicate replays existing row (no duplicate); future date rejected; list/show.
- `ReportCsvExportTest` — CSV header row; no gateway secrets; store filter.
- `ReportTenantIsolationTest` — tenant A cannot see/export/close/show tenant B.

Result: full backend suite **182 passed** (620 assertions).

Android unit tests (`android/app/src/test/`):

- `ReportDtoMappingTest` — daily sales / payment / inventory DTO field mapping.
- `ReportDisplayStateTest` — safe fallbacks, PAID-only totals, closing messages.

## Backend Compatibility Evidence

No prior endpoint changed. Sprint 0–8 tests still pass. `composer validate
--strict` passes. Health, auth, sync, sales, payments, webhooks, receipt, and
inventory routes remain registered.

## Validation Commands

```bash
bash scripts/sprint9_smoke.sh
cd backend && composer validate --strict && php artisan test && cd ..
cd android && ./gradlew :app:assembleDebug && ./gradlew :app:testDebugUnitTest && cd ..
```

## Validation Results

- Foundation/rules grep: pass
- Sprint 9 smoke: pass
- Backend `composer validate --strict`: pass
- Backend route compatibility: pass
- Backend tests: pass (182)
- Report/closing/CSV/tenant-isolation tests: pass
- Android static validation: pass
- Android assembleDebug / testDebugUnitTest: CI (JDK 21) — local skipped (JDK 25 cannot run wrapper)
- Android secret scan: pass
- Forbidden files check: pass

## GO Criteria

All 31 GO criteria in the Sprint 9 brief are satisfied: foundation remains source
of truth; Sprint 0–9 rules locked; `daily_closings` table + `DailyClosing` model
present; report services present; CSV export present; PAID-only revenue; pending
QRIS/cancelled excluded; offline-synced counted; inventory summary from ledger;
backend-generated snapshot; duplicate closing does not duplicate; tenant isolation
enforced; CSV hides secrets; Android DTO/API/repository/screen present and
lightweight/backend-authoritative; previous behaviors intact; smoke + backend
tests pass; Android CI runs assembleDebug + testDebugUnitTest.

## No-Go Checks

None triggered: rules intact, table/model/services present, closing totals
backend-only, no duplicate rows, pending QRIS excluded, cancelled excluded,
inventory from `inventory_movements`, tenant isolation enforced, CSV hides
secrets, Android does not compute authoritative totals, UI lightweight, previous
sprint behavior intact, package `com.aishtech.poslite`, minSdk 26 / targetSdk 35.

## Follow-up for Sprint 10

Sprint 10 — Subscription & Device Limit Foundation.
