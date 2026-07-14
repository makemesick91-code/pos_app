# 61 — Android Cashier Full Premium Delivery & Closure Foundation (UIX-8C)

UIX-8C is the **final premium Android delivery train** that drives the native
cashier app (`com.aishtech.poslite`, Views/XML + Retrofit/OkHttp + Room +
WorkManager + ViewModel/LiveData) to genuine UIX-7/UIX-8 runtime closure. Where
UIX-8A (rule 56) delivered the on-device design system and integer-exact money,
and UIX-8B (rule 57) rebuilt the screen surfaces, UIX-8C completes the full
premium visual rebuild, screen-by-screen state truthfulness, accessibility
hardening, and the physical-device closure campaign.

UIX-8C is delivered as a train of sprints:

- **UIX-8C-01** (this sprint) — governance, architecture, screen inventory,
  permanent foundation, foundation gate, ADR, and screen/state/accessibility
  matrix. **No visual rebuild, no physical testing, no runtime code change.**
- **UIX-8C-02 … UIX-8C-09** — implementation train (design-system hardening,
  authentication/device, cashier home & product experience, cart, payment &
  governed offline persistence, sync/WorkManager/idempotency, receipt/history,
  settings/device/printer & accessibility + physical closure).

This rule **extends and never weakens** rules 55 (UIX7-R001..R080), 56
(UIX8-R001..R048), 57 (UIX8B-R001..R100), 58 (BTPERM-R001..R029), 59
(UIX8BOPS-R001..R078), 70/72 (CI), 80 (deployment/DMS), and 90 (release/GO).
Business truth and transaction authority stay in the backend `App\Services\*`
domains and the app's canonical repositories/managers; screens and ViewModels
present and orchestrate only — never a second pricing, payment, QRIS,
settlement, or sync engine.

## Delivery-train governance
- **UIX8C-R001** — UIX-8C is the final premium Android delivery train for
  UIX-7/UIX-8 closure; every UIX-8C sprint serves that closure and none
  fabricates it.
- **UIX8C-R002** — UIX-8C does not create a separate GO tag. UIX-8C closure is
  recorded against the existing UIX-7/UIX-8 GO discipline; there is no
  `uix-8c-*-go` tag.
- **UIX8C-R003** — Historical failed physical evidence is immutable. The failed
  physical run `run-97fbb64-2af94aa` (R01 PENDING, R11 FAIL, R18 FAIL) is
  preserved verbatim and is never edited into a PASS.
- **UIX8C-R004** — Runtime changes invalidate old APK evidence. Any change to
  runtime source, dependencies, schema, or manifest requires a fresh APK build
  and fresh runtime evidence bound to the new commit + APK SHA-256.
- **UIX8C-R005** — Physical closure occurs only after code freeze. The
  physical-device runtime campaign runs against a frozen, exact-SHA candidate.
- **UIX8C-R024** — Physical testing does not start before code freeze; a
  pre-freeze physical run is developmental only and never authoritative closure.
- **UIX8C-R025** — Development emulator evidence does not replace final physical
  evidence for hardware-dependent scenarios (consistent with rule 55
  UIX7-R062, R071..R080); it is labelled emulator and never relabelled physical.

## Screen & state foundation
- **UIX8C-R006** — Every screen must define loading, empty, error, offline, and
  success states; a screen with an undefined state is not shippable.
- **UIX8C-R007** — Business logic remains outside layouts/views; XML and view
  code present and bind only.
- **UIX8C-R008** — Backend and Android canonical domain services remain
  authoritative for pricing, tax, entitlement, payment, QRIS, settlement, and
  sync; screens never recompute them.
- **UIX8C-R023** — Long tenant/outlet/product names must not break layout;
  overflow is truncated/ellipsised/wrapped, never clipped off a primary action.

## Financial & transaction integrity
- **UIX8C-R009** — Whole-Rupiah integer money remains mandatory
  (`core/money/RupiahMoney`, `Long`); no unsafe float/double on the
  authoritative money path in new/changed cashier code.
- **UIX8C-R010** — Tenant/outlet/user/device context comes from authenticated
  state (session/token/device identity), never from client-supplied input.
- **UIX8C-R011** — Cross-tenant cached identity is forbidden; account/device
  switching clears or re-scopes local tenant-scoped data.
- **UIX8C-R012** — CASH may fall back offline only for governed transport
  failures (network/timeout/unreachable); it never falls back on a canonical
  business/authorization rejection.
- **UIX8C-R013** — A canonical HTTP rejection (4xx/authoritative failure) must
  never become an offline success; only governed transport failure queues.
- **UIX8C-R014** — Cart clears only after a durable local save or a canonical
  server acknowledgement; a network failure never silently discards the cart.
- **UIX8C-R015** — A stable `clientReference` idempotency key is mandatory and
  is reused across retries, process restart, and reconnect.
- **UIX8C-R016** — Duplicate sale, payment, or inventory mutation is an
  automatic NO-GO.

## Visual & accessibility foundation
- **UIX8C-R017** — Material 3 design tokens are centralized
  (`res/values/colors.xml|dimens.xml|styles.xml|themes.xml`) and are the single
  visual source of truth.
- **UIX8C-R018** — No raw hardcoded visual values (hex colors, raw spacing,
  radius, elevation, type sizes) in new/changed layouts or view code; use the
  `@dimen` scale, `@color` tokens, `Widget.Aish.*`, and `TextAppearance.Aish.*`.
- **UIX8C-R019** — Touch targets remain at least 48dp.
- **UIX8C-R020** — TalkBack, focus order, and semantic labels are release gates.
- **UIX8C-R021** — Primary workflows remain operable at 130% font (the failure
  observed as R18 must be closed before physical PASS).
- **UIX8C-R022** — Status must not rely on colour alone; a text label always
  accompanies colour-coded state.

## Evidence & release discipline
- **UIX8C-R026** — Raw credentials, PII, tokens, and payment secrets never enter
  evidence (logs, screenshots, manifests, test artifacts).
- **UIX8C-R027** — Every UIX-8C sprint requires an authoritative exact-SHA CI
  run on its final candidate commit.
- **UIX8C-R028** — Every UIX-8C sprint PR is merged only after green gates (all
  required jobs pass, no unresolved finding, worktree clean, classifier correct).
- **UIX8C-R029** — Prior GO tags are immutable; UIX-8C never moves, deletes, or
  re-points an existing GO tag.
- **UIX8C-R030** — Absence of evidence remains NO-GO. A missing, blank, or
  generic observation stays PENDING; a FAIL/BLOCKED row stays so until genuine
  classified evidence is captured. UIX-7 remains `NO-GO — GO DEFERRED` and
  UIX-8 remains `IMPLEMENTATION COMPLETE — GO DEFERRED` until genuinely closed.

## Scope guard for UIX-8C-01
- UIX-8C-01 does not fix R11 (offline CASH durability), does not perform a broad
  runtime visual rebuild, does not modify runtime evidence/manifest state, does
  not run a physical campaign, does not build a closure APK, and does not create
  any GO tag. It establishes governance, architecture, inventory, and the
  fail-closed foundation gate only.

## ADR requirement
A material change to the delivery-train architecture, navigation graph, screen
state architecture, component architecture, adaptive layout, receipt/payment
state machine, or accessibility strategy requires an ADR under `docs/adr/`.
UIX-8C-01 is recorded by
`docs/adr/0004-uix-8c-full-premium-rebuild.md`.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists
  and that `UIX8C-R001..R030` are persisted.
- `scripts/uix8c_foundation_gate.sh` (fail-closed) validates rule persistence,
  the screen inventory, the state/accessibility matrix, the ADR, the immutable
  failed physical run record, UIX-7/UIX-8 deferred status, absent target GO
  tags, and the no-secret / no-premature-GO invariants. Its self-tests are
  `scripts/tests/uix8c_foundation_gate_test.sh`.
- Both are wired into the authoritative CI foundation lane
  (`.github/workflows/_foundation-gates.yml`).
- Because `main` is not branch-protected, GO discipline is enforced by rule and
  reviewer discipline; do not tag until every gate is genuinely met.
