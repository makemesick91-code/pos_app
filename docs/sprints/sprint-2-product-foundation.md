# Sprint 2 — Product Foundation

## Objective

Build the tenant-isolated product catalog foundation in the backend and expose
Android-ready sync endpoints, without implementing any sales/payment/inventory
runtime yet.

## Source of Truth

Canonical foundation: `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`
(sections 8, 9, 11, 12, 14, 16, 17, 21, 22, 25, 26).

## Previous Sprint Foundation Lock

- Sprint 0 — Project Setup (`docs/sprints/sprint-0-project-setup.md`), tag `sprint-0-project-setup-go`.
- Sprint 1 — SaaS Tenant Foundation (`docs/sprints/sprint-1-saas-tenant-foundation.md`), tag `sprint-1-saas-tenant-foundation-go`.

Sprint 2 builds on the Sprint 1 tenant runtime: `TenantContext` singleton,
`SetTenantContext` + `EnsureTenantIsActive` middleware, Sanctum auth, and the
`tenant.active` + `tenant.context` route middleware.

## Scope

In scope:

- `product_categories`, `products`, `product_store_prices` tables.
- `ProductCategory`, `Product`, `ProductStorePrice` models and relationships.
- Tenant/store-scoped CRUD APIs for categories, products, store price overrides.
- Android incremental product/category sync endpoints with `effective_selling_price`.
- SKU/barcode foundation, `is_stock_tracked` foundation.
- Tenant isolation enforcement + tests.
- Application rules lock (foundation + Sprint 0 + Sprint 1 + Sprint 2).

Explicitly out of scope (foundation No-Go for this sprint): sales/checkout,
QRIS/payment/webhook, offline transaction queue, printer, inventory movement
runtime, subscription billing logic, and any Android UI/networking.

## Graphify Summary

Data dependency chain and ownership:

```
Tenant ──< ProductCategory ──< Product ──< ProductStorePrice
  │             (category_id)      │  (product_id + store_id)
  └──< Store ────────────────────┘
```

- Every row carries `tenant_id`; store-scoped rows also carry `store_id`.
- Context is resolved from the authenticated user by `SetTenantContext`
  (X-Store-ID validated against the tenant). Clients never send `tenant_id`.
- Isolation is enforced by always filtering `tenant_id` on list queries and
  verifying ownership on show/update/delete (404 on mismatch). `store_id`,
  `category_id`, `product_id` inputs are validated to belong to the tenant.
- Sync endpoints merge active store price overrides into
  `effective_selling_price` and support `updated_since`.

## Backend Implementation

- Middleware/context reused from Sprint 1 (no changes to isolation core).
- Controllers inject `TenantContext` and scope every query to the tenant.
- Form Requests centralise tenant-ownership validation for `store_id`,
  `category_id`, `product_id`, plus per-tenant SKU/barcode uniqueness.
- API Resources keep payloads lightweight and never expose foreign-tenant data.
- `backend/config/pos_foundation.php` records the foundation metadata lock.

## Database Changes

New migrations:

- `..._create_product_categories_table.php`
  - `id, tenant_id, store_id (nullable), name, sort_order, is_active, timestamps`
  - indexes: `tenant_id`, `(tenant_id, store_id)`.
- `..._create_products_table.php`
  - `id, tenant_id, store_id (nullable), category_id (nullable), sku, barcode (nullable), name, unit (default pcs), cost_price (nullable), selling_price, is_stock_tracked, is_active, timestamps`
  - indexes: `tenant_id`, `(tenant_id, store_id)`, `(tenant_id, barcode)`; unique `(tenant_id, sku)`.
- `..._create_product_store_prices_table.php`
  - `id, tenant_id, store_id, product_id, selling_price, is_active, timestamps`
  - indexes: `tenant_id`, `(tenant_id, store_id)`; unique `(tenant_id, store_id, product_id)`.

Uniqueness that involves nullable columns (category name per store, barcode per
tenant) is enforced at the application layer for SQLite/PostgreSQL portability.

## Product Category API

```
GET    /api/v1/product-categories        (filters: store_id, active)
POST   /api/v1/product-categories
GET    /api/v1/product-categories/{id}
PUT    /api/v1/product-categories/{id}
DELETE /api/v1/product-categories/{id}   (sets is_active = false)
```

## Product API

```
GET    /api/v1/products                  (filters: store_id, category_id, active, q)
POST   /api/v1/products
GET    /api/v1/products/{id}
PUT    /api/v1/products/{id}
DELETE /api/v1/products/{id}             (sets is_active = false)
```

## Product Store Price API

```
GET    /api/v1/product-store-prices      (filters: store_id, product_id, active)
POST   /api/v1/product-store-prices
GET    /api/v1/product-store-prices/{id}
PUT    /api/v1/product-store-prices/{id}
DELETE /api/v1/product-store-prices/{id} (sets is_active = false)
```

## Android Product Sync API

```
GET /api/v1/sync/products     (query: updated_since, store_id)
GET /api/v1/sync/categories   (query: updated_since, store_id)
```

- Protected by `auth:sanctum` + `tenant.active` + `tenant.context`.
- Returns global (store_id null) plus selected-store rows for the tenant.
- `effective_selling_price` reflects the active store price override when present.
- `meta` includes `tenant_id`, `store_id`, `updated_since`, and `foundation`.

## Tenant Isolation Rules

- List queries always filter `tenant_id` from `TenantContext`.
- Show/update/delete verify `model.tenant_id === context.tenant_id` → 404 on mismatch.
- `store_id` / `category_id` / `product_id` inputs must belong to the tenant → 422 on mismatch.
- Sync never returns another tenant's rows; a foreign `store_id` is rejected 422.

## Application Rules Update

`docs/PROJECT_RULES.md` now contains:

- Foundation Lock Index (foundation + Sprint 0/1/2 docs).
- Sprint 0 Runtime Rule (monorepo structure).
- Sprint 1 Multi-Tenant Runtime Rule.
- Sprint 2 Product Foundation Runtime Rule.

## Testing Evidence

Feature tests (SQLite in-memory):

- `ProductCategoryApiTest` — CRUD, tenant-id spoof rejected, cross-tenant read 404, store_id ownership.
- `ProductApiTest` — CRUD/search, SKU unique per tenant, same SKU across tenants, barcode unique, category/store ownership.
- `ProductStorePriceApiTest` — create/update/delete, own-tenant store/product, unique triple.
- `ProductSyncApiTest` — tenant-only output, global + store rows, effective price override, `updated_since`, foreign store 422, foundation metadata.
- `ProductTenantIsolationTest` — tenant A cannot show/update/delete tenant B product or borrow B's category/store/product.

Result: **57 passed / 157 assertions** (full suite, including Sprint 0/1).

## Validation Commands

```bash
bash scripts/sprint2_smoke.sh
cd backend && composer validate --strict
php artisan route:list | grep -E "product-categories|products|product-store-prices|sync/products|sync/categories"
php artisan test
```

## Validation Results

- Foundation/rules grep: pass
- Sprint 2 smoke: pass
- composer validate: pass (`./composer.json is valid`)
- route:list product/sync: pass (all endpoints registered)
- backend tests: pass (57/57)
- product isolation tests: pass
- sync endpoint tests: pass
- forbidden files check: pass
- working tree clean after commit: yes

## GO Criteria

All 20 Sprint 2 GO criteria met: foundation remains source of truth; Sprint
0/1/2 rules locked in PROJECT_RULES; the three tables, three models, and four
controllers exist; category/product/store-price APIs are tenant-isolated; sync
endpoints exist with `updated_since` and `effective_selling_price`; SKU unique
per tenant; tenant A cannot reach tenant B data; smoke and tests pass; no
forbidden files; PR merged and GO tag pushed at main HEAD.

## No-Go Checks

None triggered: foundation/rules readable; Sprint 0/1 rules retained; no
cross-tenant leakage; client cannot set `tenant_id`; foreign store/category/
product rejected; isolation proven by tests; smoke, route:list, and tests pass;
no `.env`/vendor/node_modules/sqlite committed; working tree clean.

## Follow-up for Sprint 3

Sprint 3 — Android Cashier Foundation: consume `sync/products` and
`sync/categories`, render a lightweight offline-capable catalog, and begin the
cash-first cart foundation (still no QRIS/payment runtime).
