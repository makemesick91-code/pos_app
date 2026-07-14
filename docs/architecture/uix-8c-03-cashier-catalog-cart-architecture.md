# UIX-8C-03 — Cashier Home, Catalog & Cart Architecture

Component and dependency map for the UIX-8C-03 cashier home / catalog / cart
surface. Native Views/XML + ViewBinding + LiveData; **no** Compose, **no**
architecture migration, **no** new heavy dependency. Governed by rule 61
(`UIX8C-R061..R095`), extending rules 55/56/57.

## State-holder model

`CashierActivity` is the single combined catalog + cart + checkout surface. Its
state comes from one authoritative state holder, `CashierViewModel` (LiveData),
per UIX8C-R061 / UIX8B-R003. No conflicting boolean soup; persistent (cart),
screen (catalog/filter), and one-time-event state stay distinct.

`CashierViewModel` holds the filter axes `currentQuery` + `selectedCategoryId`;
`search()` and `selectCategory()` each mutate one axis and re-run the SAME
combined query via `applyFilters()`. Neither ever touches the cart
(UIX8C-R074).

## Dependency map

```
CashierActivity  (Views/XML + ViewBinding)
  │  observes LiveData, renders states, dispatches intents
  ▼
CashierViewModel  (single authoritative state holder, LiveData)
  ├─ CatalogRepository ── ProductDao ─────────── Room
  │                     └ ProductCategoryDao ─── Room
  ├─ CartRepository        (in-memory authoritative cart)
  ├─ SalesRepository       (sale creation)
  ├─ OfflineSaleRepository (offline queue — unchanged this sprint)
  ├─ StockRepository       (stock policy)
  ├─ AuthRepository ────── PosApiService (GET /api/v1/auth/me)
  └─ CatalogSyncManager    (catalog sync)
```

All Room / Retrofit I/O runs off the main thread via `viewModelScope`
(UIX8C-R092 / UIX8B-R077). Business truth stays in these repositories and the
backend `App\Services\*` domains; the ViewModel presents and orchestrates only
(UIX8C-R008/R079).

## Catalog data path (category filter)

- `ProductDao` — `getActiveProductsByCategory(categoryId, limit)` and
  `searchActiveProductsByCategory(query, categoryId, limit)`; same `isActive=1` +
  bounded `LIMIT` discipline as existing queries (UIX8C-R065).
- `ProductCategoryDao` — active categories for the chip row.
- `CatalogRepository.search(query, categoryId: Long?)` routes the (query,
  categoryId) pair to one of four Room queries (all / search / category /
  category+search). `search(query)` delegates to `search(query, null)`.
  `categories()` returns active categories.

## Context data path

- `AuthRepository.me(): ResultState<MeResponse>` → `PosApiService` →
  `GET /api/v1/auth/me` (canonical user/tenant/store).
- The pure `CashierContextPresenter.present(me, deviceName, reachable)` maps the
  response to a `CashierContext`. Client-supplied tenant/outlet identity is never
  trusted (UIX8C-R062/R063).

## Pure presenters / helpers (side-effect-free, unit-tested)

| Helper | Responsibility |
| --- | --- |
| `CashierContextPresenter.present(...)` | Map `MeResponse` + device + reachability → `CashierContext`; missing → "Tidak tersedia"; `online` only when server-resolved. |
| `CategoryOption.build(...)` | Build category chip options; "Semua" = id null; exactly one selected. |
| `emptyProductsState(query, filterActive)` | Decide `EmptyCatalog` vs `NoMatch` (filter-aware). |

Each is a pure function so it can be exhaustively covered by fast JVM tests with
no Android runtime.

## UI components

- `component_cashier_context_header.xml` — business/outlet/cashier/device lines +
  network status chip; included at the top of `activity_cashier.xml`.
- `item_category_chip.xml` — tokenized `Widget.Aish.StatusChip`, ≥48dp,
  ellipsize.
- `CategoryFilterAdapter` — horizontal RecyclerView, DiffUtil by category id,
  selection via text + colour tokens + accessibility state (never colour-only).
- `buttonClearSearch`, `buttonRetryProducts` — ≥48dp affordances with content
  descriptions.

## Change discipline

- No architecture migration (still Views/XML + ViewBinding + LiveData;
  UIX8B-R001/R002).
- No new heavy dependency added solely for visuals (UIX8-R003).
- No backend, schema, or Room offline-transaction semantics change; the added
  Room queries are read-only, bounded, and additive.
- The UIX-8C-02 responsive shell (weighted / scroll-bounded zones) is preserved.
