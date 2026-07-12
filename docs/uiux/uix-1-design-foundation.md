# UIX-1 — Design Foundation (Canonical → Platform Mapping)

Canonical source: `DESIGN_TOKENS.json`. Each canonical token maps to an Android resource and a web CSS
variable. This is the source-of-truth mapping the app implements.

## Color

| Canonical | Value | Android (`@color/…`) | Web (`--aish-…`) |
|---|---|---|---|
| brand.dark | `#0B1020` | `brand_dark` | `brand-dark` |
| brand.secondary | `#6D5DFB` | `brand_secondary` | `brand-secondary` |
| brand.support | `#06B6D4` | `brand_support` | `brand-support` |
| brand.premium | `#D6A84B` | `brand_premium` | `brand-premium` |
| action.primary | `#2563EB` | `action_primary` | `action-primary` |
| action.primaryPressed | `#1D4ED8` | `action_primary_pressed` | `action-primary-pressed` |
| action.success | `#15803D` | `action_success` | `action-success` |
| action.destructive | `#B91C1C` | `action_destructive` | `action-destructive` |
| bg.default / subtle | `#F7F9FC` / `#F1F4F9` | `bg_default` / `bg_subtle` | `bg-default` / `bg-subtle` |
| surface.default | `#FFFFFF` | `surface_default` | `surface` |
| text.primary/secondary/disabled | `#111827`/`#64748B`/`#9CA3AF` | `text_*` | `text-*` |
| border.default/subtle | `#E2E8F0` / `#EEF2F7` | `border_default` / `border_subtle` | `border` / `border-subtle` |
| status.{success,warning,danger,info} | fg+bg+border | `status_*_{fg,bg,border}` | `--aish-{status}-{fg,bg,border}` |

## Spacing / radius / touch / motion

| Token | Value | Android | Web |
|---|---|---|---|
| space xs…3xl | 4/8/12/16/24/32/48 dp | `space_xs…space_3xl` | `--aish-space-xs…3xl` |
| radius input/card/sheet/chip | 8/12/16/999 | `radius_input/card/sheet/button` | `--aish-radius-*` |
| touch min / pay | 48 / 52 dp | `touch_target_min` / `button_pay_height` | (buttons 48px) |
| motion fast/standard/emphasis | 150/220/300 ms | `motion_*` (integer) | `--aish-motion-*` |

## Typography

Family: **Inter → Roboto/system** (Inter binaries not bundled; documented fallback, UIX-R020).
Styles implemented in `styles.xml` (`TextAppearance.Aish.*`): display, pageTitle, sectionTitle, cardTitle,
body, bodySmall, label, caption, numericTable, currencyLarge. **Tabular figures** (`tnum` / `.aish-num`) are
enabled for all financial/numeric text (UIX-R005).

## Intentional platform adaptations

- **Font:** Inter → system sans-serif (license) — recorded deviation.
- **Elevation:** border-based, not heavy shadow, for entry-level device performance (UIX-R019).
- **Low-stock color:** legacy red `#C62828` → semantic `status_warning_fg` `#B45309` to match the handoff's
  "⚠ kuning" low-stock spec (intentional native adaptation).
- **Theme:** app theme moved from `DayNight` to light-first, matching the handoff's light foundation.
