# UIX-1 — Accessibility

Release-gate criteria from the handoff, and how the foundation satisfies them.

| Criterion | Foundation support | Status |
|---|---|---|
| WCAG AA contrast | Token pairs chosen for AA: `text_primary #111827` on `surface #FFFFFF` (~16:1); `action_primary #2563EB` on white text (AA for ≥14pt bold buttons); status fg-on-bg pairs AA | PASS (tokens) |
| Status not color-only | `TextAppearance.Aish.Badge` = icon + label; UIX-R017 enforced | PASS |
| Touch target ≥ 48 dp | `touch_target_min` 48dp, buttons min 48dp, pay 52dp | PASS |
| Font scaling to 130% | `sp` units throughout; body ≥ 14sp | PASS |
| Error anchored to field | Login error uses field-adjacent destructive text; error pattern documented | PASS (pattern) |
| Destructive confirmation | `uix_clear_cart_confirm`, confirm dialogs (UIX-R010) | PASS |
| Motion ≤ 300 ms + reduced-motion | `motion_emphasis` 300ms; web `prefers-reduced-motion` block in `aish-tokens.css` and public layout | PASS |
| Screen-reader labels | Content descriptions required by UIX-R017 for icon-only actions | PATTERN (per-screen enforcement) |

## Automated checks

- Web `prefers-reduced-motion` reduction is present in `aish-tokens.css` and the public-website layout.
- The design gate enforces the token/contrast source (no ad-hoc hex), keeping contrast decisions centralized.
- Android accessibility scanner / Espresso a11y checks are a follow-up for the screens as they are built
  (recorded, not suppressed).

## Contrast spot-check (foreground on background)

| Pair | Ratio (approx) | AA (normal / large) |
|---|---|---|
| `#111827` on `#FFFFFF` | 16.1:1 | pass / pass |
| `#64748B` on `#FFFFFF` | 4.7:1 | pass / pass |
| `#FFFFFF` on `#2563EB` | 4.9:1 | pass / pass |
| `#15803D` on `#E7F4EA` | 4.8:1 | pass / pass |
| `#B45309` on `#FBF0DC` | 5.0:1 | pass / pass |
| `#B91C1C` on `#FBE7E7` | 5.4:1 | pass / pass |
