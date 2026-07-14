# UIX-8C-02 — Reusable Component Library

Token-driven, accessible components reused across cashier surfaces
(UIX8C-R034/R050). No component contains business logic — they present and bind
only (UIX8C-R007). Styles live in `res/values/styles.xml`; component layouts in
`res/layout/component_*.xml`.

## Application shell

- **Bottom action region** — `Widget.Aish.BottomActionRegion` (surface +
  top-hairline `bg_bottom_action_region`, elevation via border). Hosts the
  scroll-reachable checkout CTA in the cashier shell.
- **Section header** — `Widget.Aish.SectionHeader`.
- **Cashier context header** — `component_cashier_context_header.xml`: outlet +
  cashier (ellipsized) + a network status chip (text + colour). Reusable; wired
  live by a later screen sprint (kept undriven here to avoid presenting
  unverified state — truthful UI).

## Buttons (all ≥48dp, accessible)

| Style | Use |
|-------|-----|
| `Widget.Aish.Button.Primary` | primary action (theme default) |
| `Widget.Aish.Button.Pay` | pay/confirm (success, 52dp) |
| `Widget.Aish.Button.Secondary` | outlined secondary |
| `Widget.Aish.Button.Destructive` | destructive |
| `Widget.Aish.Button.Tertiary` | low-emphasis text button |
| `Widget.Aish.Button.Icon` | icon-only, 48dp, requires a label |

## Inputs

- `Widget.Aish.TextInput` (Material 3 outlined box) and `Widget.Aish.EditText`
  (bare `EditText` — 48dp min, tokenized colours/padding) for search / cash
  tender / paid amount.

## Status

- `Widget.Aish.StatusChip` (pill, `TextAppearance.Aish.Status`) + the
  `state_*_fg/bg` token pairs cover online/offline/pending/syncing/synced/
  retrying/failed/conflict/disabled. Always **text + colour**, never colour alone
  (UIX8C-R047).

## Cards & rows

- `Widget.Aish.Card` (outlined). Money rows use `TextAppearance.Aish.MoneyTotal`
  / `MoneySecondary` (tabular figures, aligned — UIX8C-R051). Receipt rows use
  `TextAppearance.Aish.Receipt` (monospace tabular).

## State components (`component_state_*.xml`, `Widget.Aish.StateContainer`)

| Component | Contents |
|-----------|----------|
| `component_state_loading` | ProgressBar + label; announced (`cd_state_loading`). Never erases existing content. |
| `component_state_empty` | title + message; explains the next action. |
| `component_state_error` | readable error text (`cd_state_error`) + a ≥48dp retry button (`cd_state_retry`). |
| `component_state_offline` | offline status chip (text + colour) + cached-data note. |

These are the canonical loading/empty/no-result/error/offline/unavailable
surfaces (UIX8C-R050); screens toggle/overlay them rather than copying per-screen.

## Dialogs & sheets

- Bottom sheets use `ThemeOverlay.Aish.BottomSheetDialog` →
  `Widget.Aish.BottomSheet.Modal` (top-rounded `ShapeAppearance.Aish.BottomSheet`).
  The cash payment sheet (`view_payment_sheet.xml`) is a scrollable
  `NestedScrollView` so its confirm CTA stays reachable at 130%.

## Regression coverage

`DesignSystemResourceTest` asserts every style/component above exists;
`AccessibilityLayoutTest` asserts touch targets + labels + status-text-plus-colour;
`uix8c_design_system_gate.sh` fails closed if any is removed or a hardcoded value
is introduced.
