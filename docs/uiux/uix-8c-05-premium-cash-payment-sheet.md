# UIX-8C-05 — Premium Cash Payment Sheet Specification

- Sprint: UIX-8C-05
- Surface: `res/layout/view_payment_sheet.xml` + `PaymentSheetFragment.kt` (native)
- Money: whole-rupiah `Long` via `core/money/RupiahMoney.kt`

## Purpose

Define the premium native CASH payment sheet: how the cashier sees the amount due,
tenders cash (quick or manual), reads truthful change, and confirms — with every
state, design token, accessibility, and font-scale requirement made explicit. The
sheet presents and orchestrates only; it hands off to the guarded
`CashierViewModel.checkoutCash(...)` and never computes a second financial total.

## Layout & content

- **Amount due** — canonical whole-rupiah grand total, formatted only via
  `RupiahMoney.format`; never recomputed in the view.
- **Quick tender** — up to three suggested amounts from
  `QuickTenderCalculator.options(amountDue)` (STEPS `[5k, 10k, 20k, 50k, 100k]`,
  `MAX_OPTIONS = 3`, strictly above due, distinct/sorted/capped, overflow-safe).
- **Manual tender** — free-entry field parsed via `RupiahMoney.parse`
  (locale/overflow-safe, `null` on garbage/overflow — never a fabricated 0).
- **Validation** — `TenderValidator.validate(raw, amountDue)` returns
  `Empty` / `Invalid` / `Insufficient(shortBy)` / `Valid(tender, change)`.
  `canSubmit(result)` is true only for `Valid`; insufficient/invalid can never submit.
- **Change** — whole-rupiah `Valid.change`, never negative.
- **Confirm / Cancel** — confirm disabled unless `canSubmit`; cancel dismisses
  without mutating the cart.

## Payment states surfaced

The sheet renders the projection from `PaymentUiStateMapper`:

- `SubmittingOnline` — submit visibly locked (double-submit guard).
- `OnlineSuccess(sale)` — only after canonical server acknowledgement.
- `PersistingOffline` → `OfflineQueued(clientReference, grandTotal, change)` —
  shown only after a durable local commit; it is truthful "queued", never "synced".

See `docs/architecture/uix-8c-05-payment-sync-state-machine.md` for the full machine.

## Design-system usage

- Confirm CTA uses `Widget.Aish.Button.Pay`; all colour/spacing/type/shape from
  centralized tokens (`colors|dimens|styles|themes|shapes.xml`). No hardcoded hex,
  spacing, radius, elevation, or type sizes in the layout.
- Root is a `NestedScrollView` so the confirm CTA stays scroll-reachable.
- State containers reuse the canonical `component_state_*` / `Widget.Aish.StateContainer`.

## Accessibility

- All interactive targets ≥ 48dp; icon-only controls carry accessible names.
- Change / validation text uses `accessibilityLiveRegion="polite"` + a meaningful
  `contentDescription` so assistive tech announces validation and change updates.
- Status is never colour-alone; a text label always accompanies colour.
- **Focus order:** amount-due → quick-tender → manual-tender → validation → change
  → cancel → confirm.

## Font-scale resilience (100 / 115 / 130%)

- The sheet remains usable at 130%; the confirm CTA is never pushed below the fold
  (scroll-reachable via the `NestedScrollView` root).
- Money figures stay aligned/tabular via `TextAppearance.Aish.Money*`.
- Long tenant/outlet/product names wrap or ellipsize and never clip the CTA.
- The app never forces or simulates a smaller font scale to hide a layout defect.

## Rules touched

UIX8C-R131..R170, notably: QRIS online-only (R134); online success only on server
ack (R144); offline-queued only after durable commit (R145); queued/pending never
claims sync (R147); accessibility + 100/115/130% font gates (R162-R167).
