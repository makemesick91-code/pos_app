# UIX-8C-03 — Premium Cart

Cart behaviour on the combined cashier surface, delivered by UIX-8C-03. Governed
by rule 61 (`UIX8C-R061..R095`), extending rules 55/56/57. The cart presents and
orchestrates only; no pricing/tax/stock business rule is computed in the UI
(UIX8C-R079).

## Authoritative source

The in-memory `CartRepository` is the single authoritative cart source of truth
(UIX8B-R034 / UIX8C-R061). `CashierViewModel` observes it and exposes cart +
totals as LiveData; the UI never computes a different financial total than the
canonical calculation (UIX8B-R044).

## Financial integrity

- Line prices, quantities, and totals are whole-rupiah integer-exact via
  `RupiahMoney` (`Long`); no float/double on the authoritative path
  (UIX8C-R009/R073/R078).
- Prices and totals are formatted only through the canonical `RupiahMoney`
  formatter (UIX8C-R073), keeping catalog, cart, and totals visually consistent.

## Deterministic operations

Add, increment, decrement, and remove are deterministic (UIX8C-R076):

- Adding an existing line increments its quantity.
- Quantities are never zero or negative (UIX8C-R077); decrementing at 1 removes
  the line or is blocked per cart policy.
- Unavailable / out-of-stock products are not addable (UIX8C-R072).

## Clear-cart confirmation

Clear-cart requires an explicit confirmation (an `AlertDialog`) before the cart
is emptied (UIX8C-R080 / UIX8B-R036). Cancelling the dialog preserves the cart
unchanged (UIX8C-R081).

## Checkout handoff

- The checkout CTA is **disabled** when the cart is empty or in an invalid state
  (UIX8C-R085); it stays visible or scroll-reachable at 130% font
  (UIX8C-R086/R087).
- Checkout hands off to the existing cash `PaymentSheet`. **Payment logic is
  unchanged in this sprint** — this is a safe handoff only. Cash tender parsing
  (`RupiahMoney.parse`), the ViewModel double-submit guard, the stable
  `clientReference`, and durable-save-before-cart-clear all remain as delivered by
  UIX-8B (rules UIX8B-R047..R053 / UIX8C-R014..R016) and are not modified here.

## Cart preservation

The cart survives non-checkout disruptions (UIX8C-R069/R082/R083/R084):

| Event | Behaviour |
| --- | --- |
| Catalog loading | Cart preserved; a loading state never erases the cart (UIX8B-R022). |
| Catalog / product API error | Cart preserved; retry re-runs the filter only (UIX8C-R069/R084). |
| Search / category filter change | Cart never mutated (UIX8C-R074). |
| Configuration change (rotation, font scale) | Cart preserved (UIX8C-R082). |
| Supported process recreation | Cart preserved (UIX8C-R083). |

## Accessibility & layout

- Cart controls (increment/decrement/remove, clear, checkout) expose accessible
  labels and remain ≥48dp (UIX8C-R089/R090).
- Long product names in cart lines wrap or ellipsize and never clip a primary
  action (UIX8C-R088).
- Focus order reaches cart → totals → checkout after products (UIX8C-R091).

## Out of scope

- **R11 (offline CASH durability) is explicitly out of scope** this sprint and
  remains UNRESOLVED. No `SaleService`/backend/Room offline transaction semantics
  are changed. The immutable failed physical run `run-97fbb64-2af94aa`
  (R11 FAIL) is never flipped to PASS (UIX8C-R003/R058).
- On-device cart + checkout-handoff runtime validation remains a physical,
  operator-observed gate after code freeze (UIX8C-R094); this environment cannot
  run instrumented/physical Android tests.
