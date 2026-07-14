# 56 — Android Cashier Premium Visual & Transaction Foundation (UIX-8)

The Android Cashier app (`com.aishtech.poslite`, native Views/XML + Retrofit/
OkHttp + Room + WorkManager + ViewModel/LiveData) gets a **premium visual and
transaction-experience remediation**. UIX-8 formalises the on-device design
system, tightens truthful state, and hardens transaction safety, offline
durability, and idempotency **without becoming a second pricing, payment, QRIS,
settlement, or sync engine**. It extends — and never weakens — rule 55
(UIX7-R001..R080). Business truth stays in the backend `App\Services\*` domains
and the app's canonical repositories/managers; UI/ViewModels present and
orchestrate only.

This foundation is the mandatory baseline for every subsequent Android sprint.

## Native architecture
- **UIX8-R001** — The cashier surface MUST remain native Android. A WebView MUST
  NOT become the cashier transaction surface, and Blade/web consoles MUST NOT be
  wrapped as the cashier.
- **UIX8-R002** — Room/local persistence MUST remain available; WorkManager /
  offline sync MUST NOT be bypassed; device activation and tenant/outlet binding
  MUST remain native.
- **UIX8-R003** — Cart, offline queue, and sync MUST NOT move to web storage or
  browser navigation. No heavy framework may be added solely for visuals.
- **UIX8-R004** — A material architecture change (design-system, state
  management, persistent cart, navigation, payment state machine, adaptive
  layout, risk-waiver governance) requires an ADR under `docs/adr/`.

## Visual foundation (design tokens)
- **UIX8-R005** — All screens MUST use the official on-device design tokens
  (`res/values/colors.xml`, `dimens.xml`, `styles.xml`, `themes.xml`). The token
  set is Material 3-based, semantic, and already zero-hardcoded-hex in layouts.
- **UIX8-R006** — Random hardcoded colors MUST NOT be introduced in layouts or
  code. Zero raw hex in layout XML is a standing invariant.
- **UIX8-R007** — Random spacing, radius, elevation, and type sizes MUST NOT be
  introduced; use the `@dimen` scale and `TextAppearance.Aish.*` styles.
- **UIX8-R008** — Repeated UI patterns MUST use reusable component styles
  (`Widget.Aish.*`), not per-screen copies.
- **UIX8-R009** — The brand gradient MUST be used sparingly (primary header,
  primary CTA, success/brand accent) — never as a full-card or full-background
  fill.
- **UIX8-R010** — Premium design MUST NOT reduce transaction clarity; heavy
  animation MUST NOT block cashier actions.
- **UIX8-R011** — A loading state MUST NOT erase the existing cart. Product
  images MUST have a fallback and MUST NOT be required to sell.

## State foundation
- **UIX8-R012** — Each screen MUST have a single authoritative state holder
  (ViewModel). Persistent, screen, one-time-event, and sync state MUST remain
  distinct — no conflicting boolean soup.
- **UIX8-R013** — One-time events MUST NOT replay after rotation or process
  recreation.
- **UIX8-R014** — Cart MUST survive supported configuration changes and process
  recreation; network failure MUST NOT silently discard transaction state.
- **UIX8-R015** — A connectivity signal MUST NOT be treated as guaranteed server
  reachability.

## Transaction foundation (financial safety)
- **UIX8-R016** — Financial values on the authoritative path MUST use the
  canonical whole-rupiah integer type (`core/money/RupiahMoney`, `Long`). New or
  changed cashier code MUST NOT use float/double for authoritative currency
  math. Where a legacy Double column/DTO remains, the integer value is projected
  to Double at ONE documented storage/DTO boundary only — never recomputed.
- **UIX8-R017** — Cart totals, paid, change, and receipt values are computed via
  `RupiahMoney` and formatted only through `RupiahMoney.format`; a cashier-entered
  amount is parsed via `RupiahMoney.parse` (rejects garbage, never fabricates 0).
- **UIX8-R018** — A stable `clientReference` idempotency key MUST be used; a
  retry MUST reuse the same key so the backend dedupes instead of duplicating.
- **UIX8-R019** — Double-submit protection MUST exist at the ViewModel level (a
  re-entry guard, not UI-only).
- **UIX8-R020** — The cart MUST clear only after a durable local save or a
  confirmed safe transition; an offline transaction MUST survive process kill.
- **UIX8-R021** — An unknown/timeout result MUST be reconciled, never assumed
  failed and never re-rung as a fresh transaction without reconciliation.
- **UIX8-R022** — Reconnect sync MUST be idempotent; backend-persisted data
  remains the financial authority; receipt and history MUST match the persisted
  transaction.
- **UIX8-R023** — Sync retries MUST be bounded: a permanently-failing ("poison")
  row MUST stop auto-retrying after a defined cap (`OfflineSaleRepository
  .MAX_SYNC_ATTEMPTS`) so it cannot starve the queue, yet it MUST remain FAILED
  and visible (never silently dropped). PENDING and orphaned SYNCING rows are
  never capped.
- **UIX8-R024** — Tenant, outlet, device, and cashier scope MUST be enforced.
  UIX-8 MUST NOT weaken any UIX-7 idempotency or offline-durability protection.

## Product & cart
- **UIX8-R025** — Out-of-stock behaviour follows domain policy; stock indicators
  MUST NOT mislead; quantity MUST be validated.
- **UIX8-R026** — Search/filter MUST NOT alter transaction data; a product-load
  failure MUST NOT erase a valid cart.
- **UIX8-R027** — Clear-cart MUST require confirmation; checkout MUST be
  unavailable for an invalid cart state.

## Payment
- **UIX8-R028** — Only reachable payment methods MAY be displayed. QRIS MUST NOT
  be shown as active without a complete backend lifecycle, and QRIS is online-only.
- **UIX8-R029** — Cash received MUST be validated; change MUST be computed with
  the safe integer money type.
- **UIX8-R030** — Submit state MUST visibly prevent repeated action; a timeout
  MUST provide safe reconciliation/retry behaviour.

## Accessibility
- **UIX8-R031** — All meaningful controls MUST expose semantic labels; icon-only
  buttons MUST have accessible names; touch targets MUST meet the minimum size.
- **UIX8-R032** — Critical information MUST NOT rely on colour alone (a text
  label is always present).
- **UIX8-R033** — Font scaling MUST NOT hide primary actions; focus order MUST
  remain logical; error and sync status MUST be understandable by assistive
  technology.
- **UIX8-R034** — Accessibility is part of acceptance criteria and a release gate.

## Performance
- **UIX8-R035** — Blocking disk/DB/network I/O MUST NOT run on the main thread
  (Room/Retrofit on Dispatchers via coroutines/`viewModelScope`).
- **UIX8-R036** — Search MUST debounce if it triggers expensive work; lists MUST
  use stable keys; unnecessary re-render MUST be minimized; images loaded
  efficiently.
- **UIX8-R037** — APK growth MUST be reviewed; UIX-8 MUST NOT introduce an
  unresolved crash or ANR. Performance budgets MUST use measured baselines, not
  fabricated numbers.

## Security & logging
- **UIX8-R038** — Tokens, passwords, authorization headers, and credentials MUST
  NOT be logged; release builds MUST NOT expose debug-only data.
- **UIX8-R039** — Cleartext-traffic security MUST NOT be weakened (pilot/release
  TLS-only per UIX7-R045..R048); sensitive screenshot/log evidence MUST NOT be
  committed unsanitized.
- **UIX8-R040** — Tenant data MUST NOT leak across sessions or devices.

## Evidence & release (UIX-7 debt-aware)
- **UIX8-R041** — Emulator evidence MUST remain labelled emulator; hardware-
  dependent evidence follows the active hardware-classification governance
  (UIX7-R062, R071..R080). Evidence MUST bind to the exact commit SHA and APK
  checksum.
- **UIX8-R042** — A FAILED/BLOCKED/PENDING evidence row MUST NOT be flipped to
  PASS without a genuine rerun; this policy is not retroactive.
- **UIX8-R043** — UIX-7 closure debt MUST remain explicit until closed or
  formally waived. UIX-8 development is unblocked, but UIX-8 MUST NOT create a
  UIX-7 GO tag, MUST NOT alter historical UIX-7 evidence to PASS, and MUST NOT
  claim UIX-7 runtime closure is complete.
- **UIX8-R044** — UIX-8 GO requires EITHER UIX-7 closure debt resolved OR a
  formal, auditable, time-bounded product-owner risk acceptance permitted by
  release governance. A waiver MUST state residual risk, mitigation, owner,
  expiry/review, and business rationale, and MUST NOT declare UIX-7 PASS.
- **UIX8-R045** — Authoritative CI MUST run on the exact candidate SHA; local,
  origin, and VPS MUST exact-match before any GO tag; the GO tag MUST be
  annotated; prior GO tags remain immutable.
- **UIX8-R046** — DaengtisiaMS MUST remain isolated and non-regressed
  (rule 80). Shared-VPS synchronization MUST NOT touch php8.3, the `daeng` user,
  or DMS nginx/systemd/DB.
- **UIX8-R047** — The closure gate `scripts/uix8_runtime_closure_gate.sh` MUST be
  fail-closed, MUST validate identity/foundation/tests/transaction-safety/
  evidence/CI/exact-match/DMS/UIX-7-debt, and MUST have regression tests. Absence
  of proof = NO-GO.
- **UIX8-R048** — If closure or an auditable waiver is not met, the honest
  terminal state is `IMPLEMENTATION COMPLETE — GO DEFERRED`, never a fabricated GO.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists.
- `scripts/uix8_runtime_closure_gate.sh` enforces the release-gate rules above.
- Because `main` is not branch-protected, the GO discipline is enforced by rule
  and reviewer discipline; do not tag until every gate is genuinely met.
