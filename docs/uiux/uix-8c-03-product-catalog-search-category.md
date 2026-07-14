# UIX-8C-03 — Product Catalog, Search & Category Filter

Catalog, search, and category-filter behaviour delivered by UIX-8C-03. Governed
by rule 61 (`UIX8C-R061..R095`), extending rules 55/56/57. The catalog is
tenant/outlet scoped and bounded (`isActive=1` + `LIMIT`) on every query
(UIX8C-R065); the UI presents and orchestrates only (UIX8C-R079).

## Catalog states

The catalog region distinguishes ten states. Seven are the truthful primary
states enumerated by rule UIX8C-R066; the remaining three are product-card /
recovery concerns.

| # | State | Status | Notes |
| --- | --- | --- | --- |
| 1 | Loading | **Implemented** | Query in flight; never rendered as empty (UIX8C-R067). |
| 2 | Loaded | **Implemented** | Products returned. |
| 3 | Empty catalog | **Implemented** | Blank query, no active filter, zero products (`emptyProductsState` → `EmptyCatalog`). |
| 4 | No-result | **Implemented** | Active query/filter, zero matches (`emptyProductsState` → `NoMatch`); distinct from empty (UIX8C-R068). |
| 5 | Unavailable | **Implemented** | Unresolvable value → "Tidak tersedia", never a fabricated zero. |
| 6 | Offline-cached | **Implemented (labelled)** | Cached data surfaced truthfully via `cashier_products_offline_cached` (UIX8C-R070). |
| 7 | Error | **Implemented** | Load failed; `buttonRetryProducts` re-runs the current filter; cart preserved (UIX8C-R069/R084). |
| 8 | Out-of-stock (per card) | **Implemented** | Stock shown as a text label, not colour alone; not addable (UIX8C-R071/R072). |
| 9 | Missing-image (per card) | **Structural placeholder** | Fallback image; a product-image failure never blocks selling (UIX8B-R021). |
| 10 | Retry affordance | **Implemented** | Error-state retry re-runs the current filter, never clears the cart. |

Empty vs no-result is decided by the pure, unit-tested
`emptyProductsState(query, filterActive)` helper.

## Search behaviour

- `CashierViewModel.search(query)` mutates only `currentQuery` and re-runs the
  same combined query via `applyFilters()` — it **never** mutates the cart
  (UIX8C-R074).
- The `buttonClearSearch` control clears the field, which restores the catalog
  under the currently selected category (UIX8C-R075).
- Search never issues extra business work; it re-routes to the appropriate
  bounded Room query.

**Tests:** `CashierFilterStateTest` (search mutates only the query axis, cart
untouched, clear restores catalog), `CatalogRepositoryCategoryTest` (query
routing).

## Category filter behaviour

### Data layer

`CatalogRepository.search(query, categoryId: Long?)` routes the (query,
categoryId) pair to one of four bounded Room queries:

| query | categoryId | Route |
| --- | --- | --- |
| blank | null | all active products |
| set | null | `searchActiveProducts` |
| blank | set | `getActiveProductsByCategory` |
| set | set | `searchActiveProductsByCategory` |

The legacy `search(query)` delegates to `search(query, null)`.
`CatalogRepository.categories()` returns the active categories.

### ViewModel

`CashierViewModel` holds `currentQuery` + `selectedCategoryId`. `selectCategory()`
mutates only the category axis and re-runs `applyFilters()` — it **never** mutates
the cart (UIX8C-R074). The `categories` LiveData is built by the pure
`CategoryOption.build(...)`: the "Semua" chip (`cashier_category_all`) is
id `null`, and exactly one option is selected at a time.

### UI

`item_category_chip.xml` uses the tokenized `Widget.Aish.StatusChip`, a ≥48dp
touch target, and `ellipsize` for long category names (UIX8C-R088/R089).
`CategoryFilterAdapter` renders a horizontal RecyclerView, diffs by category id
(DiffUtil), and signals selection via text + colour tokens **plus** an
accessibility state description ("dipilih") — never colour alone
(UIX8C-R071/R090). Content descriptions: `cd_category_filter`,
`cd_category_selected`, `cd_category_unselected`.

### Guarantees

- Selecting or clearing a category **never** mutates the cart (UIX8C-R074).
- Clearing the filter (selecting "Semua", or clearing the search) restores the
  catalog under the current context (UIX8C-R075).
- `retry()` re-runs the current filter without touching the cart.

**Tests:** `CategoryOptionTest` (pure build: "Semua" = null id, exactly one
selected), `CashierFilterStateTest` (category mutates only its axis; cart
untouched; filter-aware empty state), `CatalogRepositoryCategoryTest` (recording
fake DAOs prove branch routing to the four Room queries).

## Strings & accessibility resources added

`cashier_category_all` ("Semua"), `cashier_search_clear`, `cashier_retry`,
`cashier_products_offline_cached`; content descriptions `cd_category_filter`,
`cd_category_selected`, `cd_category_unselected`, `cd_search_clear`,
`cd_retry_products`.

## Physical closure note

Structural/JVM tests prove state routing and layout invariants but do not replace
physical closure (UIX8C-R094). On-device catalog rendering, large-font
(100/115/130%), and TalkBack validation remain MANDATORY after code freeze; this
environment cannot run instrumented/physical Android tests.
