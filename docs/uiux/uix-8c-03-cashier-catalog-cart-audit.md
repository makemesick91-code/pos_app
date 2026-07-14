# UIX-8C-03 — Cashier Home, Catalog & Cart Initial Audit

Initial audit for **UIX-8C-03 — Premium Cashier Home, Product Catalog, Search,
Category & Cart**. Baseline commit before the sprint: `f7eab9b`. Package
`com.aishtech.poslite` (native Views/XML + ViewBinding + LiveData; **not**
Compose), JDK 21, minSdk 26, targetSdk 35. Governed by rule 61
(`UIX8C-R061..R095`), extending rules 55/56/57.

## Current architecture (pre-sprint)

- `CashierActivity` is the **single combined surface** hosting catalog, cart, and
  checkout. It is driven by `CashierViewModel` (LiveData) as the single
  authoritative state holder (UIX8B-R003 / UIX8C-R061).
- Money is integer-exact `RupiahMoney` (`Long`, whole-rupiah). Cart and totals
  never use float/double on the authoritative path (UIX8C-R009/R073/R078).
- The UIX-8C-02 responsive cashier shell is in place: weighted / scroll-bounded
  zones keep product, cart, total, and the checkout CTA visible or
  scroll-reachable at 100/115/130% font (UIX8C-R038..R043). UIX-8C-03 builds on
  those zones and must not regress them.
- Data flows through the app's canonical repositories/managers
  (`CatalogRepository`, `CartRepository`, `SalesRepository`,
  `OfflineSaleRepository`, `StockRepository`, `CatalogSyncManager`) over
  Room + Retrofit/OkHttp. Business truth stays in those repositories and the
  backend `App\Services\*` domains; the ViewModel presents and orchestrates only
  (UIX8C-R008/R079).

## UX issues addressed by this sprint

1. **No category filter.** The catalog could be searched but not narrowed by
   category, making large catalogs slow to shop. Addressed with a horizontal
   category chip row plus combined query routing.
2. **No canonical context header.** The cashier home did not surface
   business/outlet/cashier/device/network identity from the server, so the
   operator could not confirm which tenant/outlet/device they were transacting
   on. Addressed by wiring the UIX-8C-02 context header component to
   `GET /api/v1/auth/me`.
3. **No search-clear affordance.** Clearing the query required manual field
   editing. Addressed with a ≥48dp clear button.
4. **No explicit error recovery.** A product-load failure had no in-place retry.
   Addressed with an error-state retry button that re-runs the current filter and
   never clears the cart (UIX8C-R069/R084).

## Duplicated-logic review

No business logic was duplicated or forked. The sprint is presentation +
orchestration only:

- Category filtering adds **bounded Room queries** (`getActiveProductsByCategory`,
  `searchActiveProductsByCategory`) that reuse the existing `isActive=1` + `LIMIT`
  discipline; `CatalogRepository.search(query, categoryId)` only **routes** the
  pair to one of four queries. No pricing, tax, stock, or entitlement rule is
  recomputed (UIX8C-R008/R079).
- Context is **read** from canonical `GET /api/v1/auth/me` and mapped by the pure
  `CashierContextPresenter`; the UI never derives tenant/outlet identity from
  client input (UIX8C-R062/R063).
- Money is formatted only through the canonical `RupiahMoney` formatter
  (UIX8C-R073).

## Screen-state inventory (target for this sprint)

The catalog region must express distinct, truthful states (UIX8C-R066):

| State | Meaning | Distinct from |
| --- | --- | --- |
| Loading | Query in flight | Empty (UIX8C-R067) |
| Loaded | Products returned | — |
| Empty catalog | Blank query, no active filter, zero products | No-result (UIX8C-R068) |
| No-result | Query/filter active, zero matches | Empty catalog |
| Unavailable | Value not resolvable → "Tidak tersedia" | Fabricated zero |
| Offline-cached | Serving cached data, labelled truthfully | Fresh/online (UIX8C-R070) |
| Error | Load failed; retry offered, cart preserved | — (UIX8C-R069) |

Empty vs no-result is decided by the pure `emptyProductsState(query,
filterActive)` helper — only a blank query with no active filter yields
`EmptyCatalog`; any active query or category yields `NoMatch`.

## Layout risks

- The UIX-8C-02 weighted zones must remain intact when the new context header and
  category row are inserted at the top of `activity_cashier.xml`. The header and
  chip row are fixed/wrap-content bands above the scroll-bounded product region;
  cart, totals, and the checkout CTA stay visible or scroll-reachable at 130%
  font (UIX8C-R086/R087). Verified structurally by `CashierCatalogCartLayoutTest`.
- Long business/outlet/cashier/category/product names must wrap or ellipsize and
  never clip a primary action (UIX8C-R088). Category chips use `ellipsize`.

## Accessibility risks

- Category selection must not rely on colour alone; the selected chip carries a
  text/state description ("dipilih") in addition to colour tokens
  (UIX8C-R071/R090). Icon-only controls (search-clear) carry content
  descriptions.
- Touch targets (chips, clear button, retry button) must stay ≥48dp
  (UIX8C-R089).
- Focus order must follow context → search → categories → products → cart →
  totals → checkout (UIX8C-R091).

## Performance risks

- The category row and product list use RecyclerView + DiffUtil (chips keyed by
  category id) to keep rendering lightweight and bounded (UIX8C-R092).
- Room and network I/O run off the main thread via `viewModelScope`; search and
  filter re-run the same combined query rather than issuing extra work
  (UIX8C-R092).

## Implementation plan (executed)

1. Add `ProductDao.getActiveProductsByCategory` /
   `searchActiveProductsByCategory` (bounded, `isActive=1`).
2. Add `CatalogRepository.search(query, categoryId)` routing + `categories()`;
   keep `search(query)` delegating to `search(query, null)`.
3. Hold `currentQuery` + `selectedCategoryId` in `CashierViewModel`; `search()`
   and `selectCategory()` each mutate one axis and re-run `applyFilters()`;
   neither touches the cart. Build `categories` LiveData via pure
   `CategoryOption.build(...)` ("Semua" = id null; exactly one selected). Add
   filter-aware `emptyProductsState` and `retry()`.
4. Add `item_category_chip.xml` + `CategoryFilterAdapter` (tokenized, DiffUtil,
   ≥48dp, ellipsize, accessibility state).
5. Wire `component_cashier_context_header.xml` into `activity_cashier.xml`; feed
   it from `AuthRepository.me()` mapped by `CashierContextPresenter`.
6. Add `buttonClearSearch` and `buttonRetryProducts`; add strings + accessibility
   descriptions.
7. Add pure-JVM tests (`CashierContextPresenterTest`, `CategoryOptionTest`,
   `CashierFilterStateTest`, `CatalogRepositoryCategoryTest`,
   `CashierCatalogCartLayoutTest`); suite now **173 tests / 0 failures**.

## Out of scope

- R11 (offline CASH durability) is **unresolved / out of scope** this sprint.
- No `SaleService`/backend/Room offline transaction semantics change.
- No physical campaign. This environment cannot run instrumented/physical Android
  tests; automated/JVM structural tests are the proxy, not physical closure
  (UIX8C-R094). Final on-device catalog/cart + large-font (100/115/130%) +
  TalkBack validation remains MANDATORY after code freeze.
