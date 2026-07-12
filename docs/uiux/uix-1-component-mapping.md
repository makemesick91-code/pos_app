# UIX-1 — Component Mapping

Foundation components implemented as reusable Android styles + web classes. Reuse before creating new
(UIX-R016); variants are explicit.

## Android (`styles.xml`)

| Component | Style | Variants / notes |
|---|---|---|
| Primary button | `Widget.Aish.Button.Primary` | default; min 48dp; token `action_primary` |
| Pay/confirm button | `Widget.Aish.Button.Pay` | 52dp; `action_success` (cash confirm) |
| Destructive button | `Widget.Aish.Button.Destructive` | `action_destructive` |
| Secondary button | `Widget.Aish.Button.Secondary` | outlined; `border_default` |
| Text input | `Widget.Aish.TextInput` | outlined; radius 8; `border_default` |
| Card / surface | `Widget.Aish.Card` | outlined; radius 12; 0 elevation (border) |
| Status badge | `TextAppearance.Aish.Badge` | icon + label (never color alone, UIX-R017) |
| Numeric/currency | `TextAppearance.Aish.NumericTable` / `.CurrencyLarge` | tabular figures |
| Typography scale | `TextAppearance.Aish.*` | display…caption |

## Web (`aish-tokens.css`)

| Component | Class | Variants |
|---|---|---|
| Primary button | `.aish-btn-primary` | hover/active (pressed), disabled |
| Badge | `.aish-badge` | `--success` / `--warning` / `--danger` / `--info` |
| Numeric | `.aish-num` | tabular figures |

## Component states available

default · pressed · disabled · (badge) success/warning/danger/info · numeric. Loading/error/empty/offline/
locked are screen-level patterns documented in the foundation doc and coverage matrix; they are consumed by
screens as they are built, using these primitives.

## Duplication audit

No duplicate component definitions introduced. Legacy per-screen hardcoded colors (10 layout refs + 2 Kotlin
refs) were removed and re-pointed at the shared tokens, increasing reuse.
