# UIX-8C-03 — Cashier Home, Catalog & Cart Test Matrix

Test coverage for UIX-8C-03. All added tests are **pure JVM unit tests** (no
Android runtime); the full suite is now **173 tests / 0 failures**. Governed by
rule 61 (`UIX8C-R061..R095`), extending rules 55/56/57.

This environment **cannot run instrumented/physical Android tests**. JVM +
structural-XML tests are the automated proxy; they do **not** replace physical
closure (UIX8C-R094).

## Gradle tasks

- Unit tests: `testDebugUnitTest`, `testPilotUnitTest`, `testReleaseUnitTest`.
- Lint: `lintDebug`, `lintPilot`, `lintRelease`.
- Foundation gate (CI): `scripts/uix8c_cashier_catalog_cart_gate.sh`
  (fail-closed), alongside `scripts/uix8c_foundation_gate.sh` and
  `scripts/uix8c_design_system_gate.sh`.

## Coverage matrix

| Scenario | Rule(s) | Test class · method | Physical / operator-only |
| --- | --- | --- | --- |
| Context resolved → business/outlet/cashier/device mapped | R062 | `CashierContextPresenterTest` · resolved | — |
| Context missing value → "Tidak tersedia" | R062 | `CashierContextPresenterTest` · missing | — |
| Context partial (some fields absent) | R062 | `CashierContextPresenterTest` · partial | — |
| `online` true only when server-resolved (online ≠ connected) | R062 | `CashierContextPresenterTest` · online/offline | — |
| Client-supplied tenant/outlet identity never trusted | R063 | `CashierContextPresenterTest` (canonical `MeResponse` only) | — |
| Catalog routing — all products | R065 | `CatalogRepositoryCategoryTest` · all | — |
| Catalog routing — search only | R065 | `CatalogRepositoryCategoryTest` · search | — |
| Catalog routing — category only | R065 | `CatalogRepositoryCategoryTest` · category | — |
| Catalog routing — category + search | R065 | `CatalogRepositoryCategoryTest` · categorySearch | — |
| Bounded queries (`isActive=1` + `LIMIT`) | R065 | `CatalogRepositoryCategoryTest` (recording fake DAOs) | — |
| Filter-aware empty: blank + no filter → EmptyCatalog | R067/R068 | `CashierFilterStateTest` · emptyCatalog | — |
| Filter-aware empty: active query/filter → NoMatch | R068 | `CashierFilterStateTest` · noMatch | — |
| Search mutates only query axis; cart untouched | R074 | `CashierFilterStateTest` · searchNoCartMutation | — |
| Category mutates only category axis; cart untouched | R074 | `CashierFilterStateTest` · categoryNoCartMutation | — |
| Clearing filter/search restores catalog | R075 | `CashierFilterStateTest` · clearRestores | — |
| `retry()` re-runs current filter, cart preserved | R069/R084 | `CashierFilterStateTest` · retryPreservesCart | — |
| Category build: "Semua" = null id | R066 | `CategoryOptionTest` · semuaNullId | — |
| Category build: exactly one selected | R066 | `CategoryOptionTest` · exactlyOneSelected | — |
| Context header included at top of layout | R061/R062 | `CashierCatalogCartLayoutTest` · contextHeaderInclude | — |
| Category row present | R066 | `CashierCatalogCartLayoutTest` · categoryRow | — |
| Category chip touch target ≥48dp + ellipsize | R088/R089 | `CashierCatalogCartLayoutTest` · chipTouchTargetEllipsize | — |
| Search-clear affordance present | R075 | `CashierCatalogCartLayoutTest` · searchClear | — |
| Error-state retry affordance present | R069 | `CashierCatalogCartLayoutTest` · retry | — |
| Checkout CTA still scroll-reachable | R086/R087 | `CashierCatalogCartLayoutTest` · checkoutScrollReachable | — |
| No hardcoded hex in changed layouts | R033 (via R088) | `CashierCatalogCartLayoutTest` · noHardcodedHex | — |
| Status/selection not colour-alone (accessibility state) | R071/R090 | `CategoryOptionTest` + adapter state description | Visual/TalkBack confirmation |
| Money integrity (whole-rupiah `RupiahMoney` reuse) | R073/R078 | existing `RupiahMoney*` suite (unchanged, still green) | — |
| Large-font visual PASS at 100/115/130% | R086/R087 | structural proxy only | **Physical, operator-observed** |
| TalkBack focus order + spoken labels | R090/R091 | structural proxy only | **Physical, operator-observed** |
| On-device catalog/cart rendering & interaction | R061..R087 | — | **Physical, operator-observed** |

## What remains physical / operator-only

Per UIX8C-R094 and the immutable failed physical run `run-97fbb64-2af94aa`
(R01 PENDING, R11 FAIL, R18 FAIL — never flipped to PASS), the following are
**not** closed by any automated evidence in this sprint and remain MANDATORY
after code freeze:

- Large-font visual observation at 100%, 115%, and 130% system font scale
  (checkout CTA / catalog / cart visible or scroll-reachable).
- TalkBack focus order, spoken labels, and status announcements.
- On-device catalog rendering, category filtering, search, and cart interaction.
- R11 (offline CASH durability) — UNRESOLVED / out of scope this sprint.

## Release status

- UIX-8C-03: IMPLEMENTATION (pending merge / authoritative CI / deploy / tag).
- UIX-7: **NO-GO — GO DEFERRED**. UIX-8: **IMPLEMENTATION COMPLETE — GO
  DEFERRED**.
- A UIX-8C-03 sprint-scoped GO tag never asserts UIX-7/UIX-8 runtime closure
  (UIX8C-R095).
