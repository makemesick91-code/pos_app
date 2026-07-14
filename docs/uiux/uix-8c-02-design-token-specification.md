# UIX-8C-02 — Design-Token Specification

Single visual source of truth for the native Android cashier
(`com.aishtech.poslite`). All tokens live in `android/app/src/main/res/values/`
(`colors.xml`, `dimens.xml`, `styles.xml`, `themes.xml`, `shapes.xml`). New or
changed UI must reference tokens only — no raw hex/dp/sp (UIX8C-R032/R033/R035),
enforced by `scripts/uix8c_design_system_gate.sh`.

## Colour (`colors.xml`)

Brand: `brand_dark`, `brand_secondary`, `brand_support`, `brand_premium`,
`accent_gold` (refined gold — sparing accent only, never a large fill).
Action: `action_primary(_pressed)`, `action_success(_pressed)`,
`action_destructive`. Surface: `bg_default`, `bg_subtle`, `surface_default`.
Text: `text_primary/secondary/disabled/on_dark*`. Border: `border_default/subtle`,
`divider`.

**Transaction / sync state tokens (UIX-8C-02, UIX8C-R047)** — each state has a
distinct accessible `fg`/`bg` pair drawn from the status triads; status is always
paired with a text label (never colour-alone):

| State | fg / bg | Semantic |
|-------|---------|----------|
| online / synced | `state_online_fg` / `state_online_bg`, `state_synced_*` | positive/settled (green) |
| pending / syncing | `state_pending_*`, `state_syncing_*` | in-progress (blue) |
| offline / retrying / conflict | `state_offline_*`, `state_retrying_*`, `state_conflict_*` | attention (amber) |
| failed | `state_failed_fg` / `state_failed_bg` | error (red) |
| disabled | `state_disabled_fg` / `state_disabled_bg` | muted |

## Typography (`styles.xml` + `dimens.xml`)

System-scalable `sp` sizes (respect font scaling, UIX8C-R035). Roles:
`TextAppearance.Aish.Display/PageTitle/SectionTitle/CardTitle/Body/BodySmall/
Label/Caption/Badge`, numeric `NumericTable/CurrencyLarge`, and the UIX-8C-02
money/status/receipt roles: `MoneyTotal` (large tabular total), `MoneySecondary`
(row amount), `Status` (state label), `Receipt` (monospace tabular). Sizes:
`text_display 28`, `text_page_title 22`, `text_section_title 17`,
`text_card_title 15`, `text_body 14`, `text_body_small 13`, `text_label 12`,
`text_caption 11.5`, `text_currency_large 30`, `text_numeric_table 13` (sp).

## Spacing (`dimens.xml`)

`space_2xs 2`, `space_xs 4`, `space_sm 8`, `space_md 12`, `space_lg 16`,
`space_lg_plus 20`, `space_xl 24`, `space_2xl 32`, `space_3xl 48`, `hairline 1`
(dp). The 2/20 steps were added so every inset resolves to a token.

## Shape (`shapes.xml`)

`ShapeAppearance.Aish.SmallComponent` (8dp), `MediumComponent` (12dp),
`LargeComponent` (16dp), `Pill` (999dp — status chips), `BottomSheet`
(top-rounded 16dp), `Dialog` (16dp). Wired into the theme via
`shapeAppearanceSmall/Medium/LargeComponent`.

## Elevation (`dimens.xml`)

`elevation_none 0`, `elevation_raised 1`, `elevation_sheet 3` (dp). Light system:
elevation is expressed via border/surface, not heavy shadow (UIX8C-R017).

## Motion (`dimens.xml`)

`motion_fast 150`, `motion_standard 220`, `motion_emphasis 300` (ms). Bounded,
subtle, never blocking a cashier action (UIX8C-R053).

## Responsive shell dimens

`cashier_product_min_height 96`, `cashier_action_region_min_height 88`,
`status_chip_min_height 28`, `radius_pill 999` (dp). Touch targets:
`touch_target_min 48`, `button_height 48`, `button_pay_height 52`,
`input_height 48`, `stepper_size 48` — all ≥48dp (UIX8C-R044).

## Theme (`themes.xml`)

`Theme.AishPosLite` (Material 3 Light) maps colours to tokens, wires the shape
families, sets `materialButtonStyle = Widget.Aish.Button.Primary`, and a governed
`ThemeOverlay.Aish.BottomSheetDialog` (top-rounded sheet shape). Dynamic colour
does not override the brand identity unless ADR-approved (UIX8C-R054).
