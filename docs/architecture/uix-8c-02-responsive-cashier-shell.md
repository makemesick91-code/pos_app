# UIX-8C-02 — Responsive Cashier Shell (R18 Fix)

## The failure (physical run `run-97fbb64-2af94aa`, R18 FAIL)

At 130% system font the cashier layout collapsed: the checkout CTA was pushed
off-screen and unreachable. Root cause (`activity_cashier.xml`, pre-fix):

```
LinearLayout (vertical, match_parent, NON-scrollable)
  ├─ title / sync rows / search        (wrap_content, grow with font)
  ├─ product FrameLayout               (0dp, weight=1)   ← only spring
  ├─ divider / cart count / cart total (wrap_content)
  ├─ paid-amount input                 (wrap_content)
  ├─ [ Clear | Checkout ] row          (wrap_content)
  ├─ offline-checkout CTA              (wrap_content)   ← pushed off-screen
  └─ result text                       (wrap_content)
```

When the fixed bottom stack grows at large font, the single weighted product
region collapses first; once the stack exceeds the screen the bottom CTAs
overflow a root that cannot scroll → **unreachable checkout**.

## The fix — dual weighted scroll shell

```
LinearLayout (vertical, match_parent)
  ├─ ZONE 1  fixed compact header: title + sync rows + search
  ├─ ZONE 2  product region      : FrameLayout(0dp, weight=3, minHeight=96dp)
  │                                └─ RecyclerView (internal scroll) + progress + empty
  └─ ZONE 3  action region       : NestedScrollView(0dp, weight=2, minHeight=88dp,
                                    id=cartActionScroll, fillViewport, BottomActionRegion)
                                    └─ cart count · total(MoneyTotal) · paid input
                                       · [Clear|Checkout] · offline CTA · result
```

Invariant: **both** flexible zones are `0dp`-weighted scroll containers with a
`minHeight`. The vertical stack therefore can never exceed the screen at any font
scale — Zone 2 scrolls its product list, Zone 3 scrolls its cart/CTA block. The
checkout CTA lives **inside** Zone 3, so it is always visible or scroll-reachable
(UIX8C-R039). The compact fixed header stays bounded (≤ ~280dp at 130%) and the
two `minHeight`s (96+88=184dp) fit within any supported cashier phone height.

Why not one big `ScrollView`? A `RecyclerView` inside a plain `ScrollView` breaks
recycling and creates nested-scroll dead zones (UIX8C-R043). The dual weighted
scroll shell is the correct pattern.

## Payment sheet

`view_payment_sheet.xml` root is now a `NestedScrollView` (`fillViewport`) so the
Pay/confirm CTA can never drop below the sheet fold at 130% (UIX8C-R040).

## Preservation guarantees

- **All view IDs preserved** (`buttonCheckout`, `inputPaidAmount`,
  `textCartTotal`, `listProducts`, …) → `CashierViewModel` /
  `PaymentSheetFragment` ViewBinding wiring unchanged. **No business-logic,
  pricing, payment, offline, or sync change.** Double-submit guard, stable
  `clientReference`, durable-save-before-cart-clear all untouched.
- Money uses integer-exact `RupiahMoney`; the shell only re-parents presentation.

## Verification

- `FontScaleLayoutTest` (JVM) asserts the structural invariant: weighted product
  region with `minHeight`, `NestedScrollView` action region with `minHeight`, and
  both checkout CTAs inside the scroll region; payment-sheet root scrollable.
- `uix8c_design_system_gate.sh` fails closed if the shell is de-scrolled.
- Structure is the machine-checkable proxy for "operable at 100/115/130%". Final
  **visual** confirmation at each scale on a physical device is operator-performed
  after code freeze (UIX8C-R056/R059); this sprint does not flip physical R18 to
  PASS (UIX8C-R058).

## Tablet / adaptive note

Phone portrait is the mandatory baseline (UIX8C-R055). The weighted shell already
adapts to width; a dedicated tablet two-pane layout is deferred to a later screen
sprint and must not regress phone portrait.
