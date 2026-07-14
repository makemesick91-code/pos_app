# UIX-8C-03 — Premium Cashier Home & Context Header

Design of the premium cashier home surface and its canonical context header,
delivered by UIX-8C-03. Native Views/XML + ViewBinding + LiveData; single
`CashierActivity` / `CashierViewModel`. Governed by rule 61
(`UIX8C-R061..R095`), extending rules 55/56/57.

## Composition

`activity_cashier.xml` composes the home as vertical bands over the UIX-8C-02
responsive shell (weighted / scroll-bounded zones):

1. **Context header** (`component_cashier_context_header.xml`, included at the
   top) — business / outlet / cashier / device text lines + a network status
   chip.
2. **Search row** — the product search field plus a `buttonClearSearch` (≥48dp,
   `cd_search_clear`).
3. **Category row** — a horizontal `RecyclerView` of category chips
   (`CategoryFilterAdapter`).
4. **Product region** — the scroll-bounded catalog list (loading / loaded /
   empty / no-result / offline-cached / error states), including
   `buttonRetryProducts` in the error state.
5. **Cart / totals / checkout** — the cart summary, whole-rupiah totals, and the
   checkout CTA, kept visible or scroll-reachable at 130% font
   (UIX8C-R086/R087).

The context header and category/search bands are fixed / wrap-content above the
scroll-bounded product region, so inserting them does not push the checkout CTA
off-screen. Structural invariants are asserted by `CashierCatalogCartLayoutTest`.

## Canonical context sourcing

The header is fed only from canonical authenticated state, never from client
input (UIX8C-R062/R063):

- `AuthRepository.me(): ResultState<MeResponse>` calls `GET /api/v1/auth/me`,
  returning the canonical `user` / `tenant` / `store`.
- The pure `CashierContextPresenter.present(me, deviceName, reachable)` maps the
  response to a `CashierContext` (business, outlet, cashier, device, network).
- The mapping is deterministic and side-effect-free; it is unit-tested by
  `CashierContextPresenterTest` (resolved / missing / partial / online-offline).

The cashier surface never inherits Platform Admin (`/admin/*`) or Tenant Owner
(`/owner/*`) authorization or controls (UIX8C-R064); it authenticates with the
Sanctum API token per rule 55.

## Truthful values & network semantics

- A missing or unresolvable field renders **"Tidak tersedia"** (`ctx_unavailable`),
  never a fabricated value or zero (UIX8C-R062, aligned with UIX7-R024).
- `online` is `true` **only** when identity was resolved from the server this
  session. Mere connectivity is not treated as server reachability
  (online ≠ merely connected; UIX8B-R026). The network status chip carries a text
  label, never colour alone (UIX8C-R071/R047).

## Accessibility

- The header container and its salient lines expose content descriptions
  (`cd_context_header`, `cd_context_business`, `cd_context_device`)
  (UIX8C-R090).
- The network status is conveyed by text + colour, never colour alone
  (UIX8C-R071/R047).
- Focus order begins at the context header and proceeds context → search →
  categories → products → cart → totals → checkout (UIX8C-R091).
- Interactive targets remain ≥48dp (UIX8C-R089).

## Font-scale resilience

- The header uses `sp` typography and wraps/ellipsizes long business, outlet,
  cashier, and device names (UIX8C-R088), so a long tenant name never clips a
  primary action.
- At 100/115/130% font the header, search, category row, product region, cart,
  totals, and checkout CTA remain visible or scroll-reachable (UIX8C-R086/R087),
  preserving the UIX-8C-02 shell. This is verified structurally in CI;
  **visual large-font PASS remains a physical, operator-observed gate** after
  code freeze (UIX8C-R094).

## Strings & accessibility resources added

`ctx_business_label`, `ctx_unavailable` ("Tidak tersedia"),
`cashier_search_clear`, `cashier_products_offline_cached`; content descriptions
`cd_context_header`, `cd_context_business`, `cd_context_device`,
`cd_search_clear`.
