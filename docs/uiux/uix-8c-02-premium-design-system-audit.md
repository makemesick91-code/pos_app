# UIX-8C-02 — Premium Design-System Audit

Sprint: UIX-8C-02 (Premium Design-System Hardening, Responsive Shell & Component
Library). Module: `com.aishtech.poslite` (native Views/XML + Retrofit/OkHttp +
Room + WorkManager + ViewModel/LiveData). This audit drove the sprint edits;
findings are captured verbatim so the remediation is auditable.

## Method

- **Graphify**: `graphify update android` refreshed the repo dependency graph
  (`graphify-out/graph.json` + `GRAPH_REPORT.md`). Graphify models Kotlin/source
  coupling (Activities → repositories/DTOs) but does **not** model
  layout↔style↔token relationships, so the token/style audit used a targeted
  `rg`/`find` static scan over `res/values` and `res/layout`. Graphify status:
  **available and used** for source coupling; static-scan fallback used for the
  design-system surface (documented per the sprint's fallback discipline).
- Static scan of every `res/values*/*.xml` and `res/layout/*.xml`, every feature
  Activity/Fragment/Adapter, `build.gradle.kts` variants, and the existing unit
  test source set.

## Existing design foundation (UIX-1 → UIX-8B) — already clean

- `Theme.AishPosLite` extends `Theme.Material3.Light.NoActionBar`; the
  `TextAppearance.Aish.*` and `Widget.Aish.*` families already exist.
- Semantic colour tokens (brand/action/bg/surface/text/border/status), a 4dp
  spacing grid, 48/52dp touch targets, and motion integers are defined.
- **Zero hardcoded hex** in layouts; **zero sub-48dp** touch targets; **no
  icon-only controls** missing content descriptions.

This is a **hardening** sprint, not a greenfield build.

## Design debt found (and remediated this sprint)

| # | Finding | Location | Remediation |
|---|---------|----------|-------------|
| D1 | **R18 root cause** — cashier root is a non-scrollable vertical `LinearLayout`; the tall fixed bottom stack (cart count/total/paid input/checkout CTAs) pushes the checkout CTA off-screen at 130% font because the single weighted product region is the only spring. | `activity_cashier.xml` | Responsive shell: weighted product region + weighted internally-scrollable bottom action region (`NestedScrollView`), both with `minHeight`. CTA is now inside the scroll region. |
| D2 | Payment sheet root is a non-scrollable `LinearLayout`; the Pay CTA can drop below the sheet fold at 130%. | `view_payment_sheet.xml` | Root wrapped in a `NestedScrollView` (`fillViewport`). |
| D3 | Two raw `2dp` insets. | `item_product.xml:36,45` | `@dimen/space_2xs` (new token). |
| D4 | One raw `20dp` margin. | `activity_login.xml:63` | `@dimen/space_lg_plus` (new token). |
| D5 | No explicit per-state sync/transaction colour tokens (offline/pending/syncing/synced/failed/conflict/disabled) — states re-derived per screen. | `colors.xml` | Added `state_*_fg/bg` token family (accessible triads). |
| D6 | No centralized shape family; no money/status/receipt text roles; no tertiary/icon button, `EditText`, status-chip, section-header, bottom-action-region, or state-container styles. | `styles.xml`, new `shapes.xml` | Added `ShapeAppearance.Aish.*`, `TextAppearance.Aish.Money*/Status/Receipt`, and the missing `Widget.Aish.*` styles. |
| D7 | No reusable loading/empty/error/offline state components; no reusable context header. | `res/layout` | Added `component_state_*` + `component_cashier_context_header`. |
| D8 | No instrumented/JVM regression net for the design system or large-font layout. | `src/test` | Added `DesignSystemResourceTest`, `FontScaleLayoutTest`, `AccessibilityLayoutTest` + the fail-closed `uix8c_design_system_gate.sh`. |

## Accessibility gaps

- No icon-only controls missing labels; touch targets already ≥48dp. Residual:
  the receipt block-reason relies on colour + text (it already carries text, so
  it is not colour-alone). Status colours now have named tokens so any chip pairs
  text + colour (UIX8C-R047).

## Responsive layout risks

- Confined to the two non-scrollable roots (D1, D2). `activity_login`,
  `activity_reports`, `activity_subscription_status`, `activity_qris_payment` are
  `ScrollView` roots (large-font safe); `activity_transaction_history` and the
  receipt CTA are already safe (weighted RecyclerView / bottom-pinned CTA).

## Performance risks

- Native Views/XML; no Compose. No large fixed-height containers, no oversized
  images, no main-thread I/O introduced. The shell adds one `NestedScrollView`
  per affected screen (negligible). APK growth is limited to XML/resources.

## Migration strategy

Adopt tokens + reusable shell on the two R18-risk screens only (IDs preserved →
no ViewModel/binding change). Full per-screen rebuilds continue in UIX-8C-03..09.
The design system is enforced by `uix8c_design_system_gate.sh` so regressions are
CI-blocking. Physical large-font confirmation is deferred to the post-freeze
physical campaign (UIX8C-R059); this sprint does not close R18 or R11.
