# 57 — Android Cashier Native Premium Screen Rebuild (UIX-8B)

UIX-8B completes the native premium cashier **screen** rebuild begun by UIX-8A
(rule 56). Where UIX-8A delivered the on-device design system, integer-exact
money, bounded retry, and transaction-safety foundation, UIX-8B rebuilds the
actual cashier **surfaces** — home, product experience, cart, payment,
success/receipt, history — to a premium, accessible, truthful standard.

It **extends and never weakens** rules 55 (UIX7-R001..R080) and 56
(UIX8-R001..R048). The cashier stays native Android (Views/XML + Retrofit/OkHttp
+ Room + WorkManager + ViewModel/LiveData). Business truth stays in the backend
`App\Services\*` domains and the app's canonical repositories/managers; screens
and ViewModels present and orchestrate only — never a second pricing, payment,
QRIS, settlement, or sync engine.

This screen-and-experience foundation is the mandatory baseline for every
subsequent Android sprint.

## Native screen foundation
- **UIX8B-R001** — All cashier transaction screens MUST remain native Android.
- **UIX8B-R002** — A WebView MUST NOT be used for cashier workflows, and Blade/
  web consoles MUST NOT be wrapped as the cashier.
- **UIX8B-R003** — Each screen's state MUST come from a single authoritative
  state holder (ViewModel); no conflicting boolean soup.
- **UIX8B-R004** — Navigation MUST NOT create duplicate checkout submissions.
- **UIX8B-R005** — Navigation MUST NOT show stale receipt/transaction data.
- **UIX8B-R006** — Back navigation MUST preserve safe cart state.
- **UIX8B-R007** — Repeated navigation MUST be idempotent.
- **UIX8B-R008** — Screen recreation MUST NOT replay one-time events.
- **UIX8B-R009** — Runtime errors MUST have an actionable recovery path.
- **UIX8B-R010** — A generic toast MUST NOT be the only error UX.

## Visual foundation
- **UIX8B-R011** — All screens MUST use the official on-device design tokens
  (`res/values/colors.xml|dimens.xml|styles.xml|themes.xml`).
- **UIX8B-R012** — Hardcoded colors MUST NOT be introduced in layouts or code.
- **UIX8B-R013** — Hardcoded spacing/radius/elevation/typography MUST NOT be
  introduced; use the `@dimen` scale and `TextAppearance.Aish.*`.
- **UIX8B-R014** — The existing Material 3 foundation MUST be reused, not forked.
- **UIX8B-R015** — Repeated UI patterns MUST use reusable component styles
  (`Widget.Aish.*`), not per-screen copies.
- **UIX8B-R016** — Components MUST support accessibility semantics.
- **UIX8B-R017** — Premium styling MUST NOT reduce transaction clarity.
- **UIX8B-R018** — The brand gradient MUST remain limited (primary header,
  primary CTA, selected brand state, success accent) — never a full-card/
  full-background fill.
- **UIX8B-R019** — Heavy shadow MUST NOT be used as the primary hierarchy signal
  (elevation via border/surface, per the light system).
- **UIX8B-R020** — Heavy animation MUST NOT delay transaction actions.
- **UIX8B-R021** — Product image failure MUST NOT block checkout.
- **UIX8B-R022** — A loading state MUST NOT erase the cart.
- **UIX8B-R023** — Empty states MUST explain the next action.
- **UIX8B-R024** — Error states MUST provide retry or recovery.

## Cashier-home foundation
- **UIX8B-R025** — Cashier home MUST show outlet, cashier, device, network, and
  sync state.
- **UIX8B-R026** — Connectivity MUST NOT be treated as guaranteed server
  reachability (online ≠ reachable).
- **UIX8B-R027** — Product search MUST NOT mutate cart state.
- **UIX8B-R028** — Category filtering MUST NOT mutate cart state.
- **UIX8B-R029** — A product-list refresh failure MUST NOT erase a valid cart.
- **UIX8B-R030** — Product cards MUST reflect stock policy accurately.
- **UIX8B-R031** — Out-of-stock actions MUST follow domain policy.
- **UIX8B-R032** — Low-stock display MUST NOT fabricate threshold logic.

## Cart foundation
- **UIX8B-R033** — Cart MUST use integer-exact money (`RupiahMoney`, `Long`).
- **UIX8B-R034** — Cart MUST remain one authoritative source of truth.
- **UIX8B-R035** — Add/increment/decrement/remove MUST be deterministic.
- **UIX8B-R036** — Clear cart MUST require confirmation.
- **UIX8B-R037** — Cart MUST survive background.
- **UIX8B-R038** — Cart MUST survive supported process recreation.
- **UIX8B-R039** — Cart MUST NOT clear before a durable transaction save.
- **UIX8B-R040** — Product refresh MUST NOT destroy the current cart.
- **UIX8B-R041** — Invalid quantity MUST block checkout.
- **UIX8B-R042** — Invalid stock state MUST follow backend/domain policy.
- **UIX8B-R043** — Cart summary MUST match the transaction request.
- **UIX8B-R044** — Cart UI MUST NOT compute a different financial total than the
  canonical calculation.

## Payment foundation
- **UIX8B-R045** — Only reachable payment methods MAY be shown.
- **UIX8B-R046** — QRIS MUST remain hidden until a complete backend lifecycle
  exists; QRIS is online-only.
- **UIX8B-R047** — Cash input MUST use `RupiahMoney.parse` (never fabricate 0).
- **UIX8B-R048** — Tendered cash MUST be validated.
- **UIX8B-R049** — Change MUST use integer-exact arithmetic.
- **UIX8B-R050** — Checkout MUST have in-flight submit protection (ViewModel-
  level re-entry guard, not UI-only).
- **UIX8B-R051** — A retry MUST reuse the stable `clientReference`.
- **UIX8B-R052** — An unknown result MUST reconcile before a new submission.
- **UIX8B-R053** — A timeout MUST NOT silently create a second transaction.
- **UIX8B-R054** — Payment success MUST bind to the current transaction.
- **UIX8B-R055** — A failure state MUST preserve recoverable transaction context.

## Receipt/history foundation
- **UIX8B-R056** — The receipt MUST bind to the current transaction.
- **UIX8B-R057** — The receipt MUST NOT display a stale prior result.
- **UIX8B-R058** — The receipt MUST match the persisted transaction.
- **UIX8B-R059** — History MUST show each transaction exactly once.
- **UIX8B-R060** — Pending/synced/failed states MUST be explicit.
- **UIX8B-R061** — History retry MUST remain idempotent.
- **UIX8B-R062** — History MUST remain tenant/outlet scoped.
- **UIX8B-R063** — Receipt and history MUST use the same money formatter.
- **UIX8B-R064** — Receipt/history status MUST NOT rely on colour alone.

## Accessibility foundation
- **UIX8B-R065** — Every interactive control MUST expose an accessible name.
- **UIX8B-R066** — Icon-only controls MUST have labels.
- **UIX8B-R067** — Focus order MUST follow the operational flow.
- **UIX8B-R068** — Touch targets MUST meet the minimum size.
- **UIX8B-R069** — Critical state MUST NOT rely on colour alone.
- **UIX8B-R070** — Font scaling MUST NOT hide primary actions.
- **UIX8B-R071** — The checkout total MUST remain readable at large font.
- **UIX8B-R072** — The receipt MUST remain readable at large font.
- **UIX8B-R073** — History MUST remain usable at large font.
- **UIX8B-R074** — TalkBack MUST understand offline/pending/synced states.
- **UIX8B-R075** — Error messages MUST be announced meaningfully.
- **UIX8B-R076** — Accessibility is a release acceptance criterion.

## Performance foundation
- **UIX8B-R077** — Blocking I/O MUST NOT run on the main thread (Room/Retrofit
  on Dispatchers via coroutines/`viewModelScope`).
- **UIX8B-R078** — Product lists MUST use stable keys.
- **UIX8B-R079** — Search SHOULD debounce expensive work.
- **UIX8B-R080** — Recomposition/re-render MUST be bounded.
- **UIX8B-R081** — Image loading MUST be cached and non-blocking.
- **UIX8B-R082** — APK growth MUST be reviewed.
- **UIX8B-R083** — No unresolved crash/ANR MAY ship.
- **UIX8B-R084** — Screen transitions MUST NOT block transaction flow.

## Evidence and release foundation (UIX-7 debt-aware)
- **UIX8B-R085** — Runtime evidence MUST bind to the exact commit SHA and APK
  SHA-256.
- **UIX8B-R086** — Emulator evidence MUST remain labelled emulator (rule 55
  UIX7-R062, R071..R080).
- **UIX8B-R087** — Operator-observed evidence MUST require an explicit human PASS.
- **UIX8B-R088** — Screenshot existence alone MUST NOT equal PASS.
- **UIX8B-R089** — Evidence observation MUST be substantive.
- **UIX8B-R090** — Transaction-chain evidence MUST share a single run ID and
  `clientReference`.
- **UIX8B-R091** — UIX-7 debt MUST remain explicit until closed or waived.
- **UIX8B-R092** — UIX-8 GO MUST require UIX-7 debt closure or a valid,
  auditable, time-bounded product-owner waiver (never declaring UIX-7 PASS).
- **UIX8B-R093** — Authoritative CI MUST run on the exact release candidate SHA.
- **UIX8B-R094** — Local, origin, and VPS MUST exact-match before any GO tag.
- **UIX8B-R095** — DaengtisiaMS non-regression MUST be verified (rule 80).
- **UIX8B-R096** — The GO tag MUST be annotated.
- **UIX8B-R097** — Prior GO tags MUST remain immutable.
- **UIX8B-R098** — A failed gate MUST remain NO-GO / GO_DEFERRED.
- **UIX8B-R099** — Absence of proof MUST remain NO-GO.
- **UIX8B-R100** — This UIX-8B screen/experience foundation becomes the mandatory
  baseline for every subsequent Android sprint.

## ADR requirement
A material change to navigation, screen architecture, component architecture,
adaptive layout, receipt state binding, payment state machine, or accessibility
strategy requires an ADR under `docs/adr/`.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists.
- `scripts/uix8_runtime_closure_gate.sh` enforces the UIX-8/8B release gate
  (fail-closed). The GO tag target remains
  `uix-8-android-cashier-premium-visual-transaction-experience-go`.
- Because `main` is not branch-protected, GO discipline is enforced by rule and
  reviewer discipline; do not tag until every gate is genuinely met.
