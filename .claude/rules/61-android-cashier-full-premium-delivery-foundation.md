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
- **UIX8C-R002** — UIX-8C has **no single umbrella or final GO tag**. Each
  UIX-8C implementation sprint MAY carry an immutable, annotated, **sprint-scoped
  GO tag** (pattern `uix-8c-NN-<slug>-go`, e.g.
  `uix-8c-02-premium-design-system-hardening-go`) once that sprint is merged with
  authoritative exact-SHA CI green, local/origin/VPS exact-match, and its sprint
  gates PASS. A sprint-scoped tag records only that sprint's implementation
  closure: it **never asserts UIX-7 or UIX-8 runtime closure**, never replaces
  physical-device evidence, and never opens final release. UIX-8C's UIX-7/UIX-8
  closure is still recorded against the existing UIX-7/UIX-8 GO discipline; no
  umbrella `uix-8c-*-go` closure tag is ever minted (refined by UIX8C-R060).
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

## UIX-8C-02 — Premium design-system, responsive shell & accessibility foundation
Introduced by UIX-8C-02 (premium design-system hardening, responsive cashier
shell, and reusable component library). These rules are the permanent visual /
responsive / accessibility baseline for every subsequent Android sprint and
extend — never weaken — rules 55/56/57/58/59 and UIX8C-R001..R030.

### Visual system
- **UIX8C-R031** — Material 3 is the canonical Android visual foundation
  (`Theme.AishPosLite` extends `Theme.Material3.*`); no non-Material visual
  framework is introduced for cashier surfaces.
- **UIX8C-R032** — Aish brand colour, typography, spacing, shape, elevation, and
  motion tokens are centralized in
  `res/values/colors.xml|dimens.xml|styles.xml|themes.xml|shapes.xml` and are the
  single visual source of truth.
- **UIX8C-R033** — New or changed UI must not introduce raw off-system colour
  values (no hardcoded hex in layouts or view code); use `@color` tokens.
- **UIX8C-R034** — New or changed UI must not duplicate canonical component
  styles locally; reuse `Widget.Aish.*` and `TextAppearance.Aish.*`.

### Font-scale resilience
- **UIX8C-R035** — Typography respects Android system font scaling (`sp` sizes;
  no `dp` type sizes on the authoritative path).
- **UIX8C-R036** — The application must never force or simulate a smaller user
  font scale to hide layout defects.
- **UIX8C-R037** — Supported primary workflows remain operable at 100%, 115%, and
  130% system font scale.
- **UIX8C-R038** — The product catalog remains visible or scroll-reachable at
  130%.
- **UIX8C-R039** — Cart, totals, and the checkout CTA remain visible or
  scroll-reachable at 130% (the checkout CTA is never pushed off-screen).
- **UIX8C-R040** — The payment sheet remains usable at 130% (its confirm CTA is
  scroll-reachable, never below the sheet fold).
- **UIX8C-R041** — Receipt and transaction history remain usable at 130%.

### Layout integrity
- **UIX8C-R042** — Critical content must not depend on unsafe fixed-height
  containers; flexible regions are weighted or scroll-bounded, not clipped.
- **UIX8C-R043** — Nested scrolling must be explicit, bounded, and free of dead
  zones (no RecyclerView inside a plain ScrollView; scroll regions are weighted).
- **UIX8C-R044** — All interactive touch targets remain at least 48dp.
- **UIX8C-R049** — Long tenant, outlet, cashier, category, and product names wrap
  or ellipsize safely and never clip a primary action.

### Accessibility
- **UIX8C-R045** — Icon-only controls carry meaningful accessible labels.
- **UIX8C-R046** — Focus order follows the visible cashier workflow.
- **UIX8C-R047** — Status, error, offline, and sync states are not conveyed by
  colour alone; a text label always accompanies colour-coded state.
- **UIX8C-R048** — Error states expose readable text and accessible
  announcements.

### Component & money presentation
- **UIX8C-R050** — Loading, empty, no-result, error, offline, and unavailable
  states use canonical reusable components (`component_state_*`,
  `Widget.Aish.StateContainer`), not per-screen copies.
- **UIX8C-R051** — Whole-Rupiah financial values remain visually stable, aligned,
  and unambiguous (tabular figures via `TextAppearance.Aish.Money*`).
- **UIX8C-R052** — Decorative elements must never obscure operational or financial
  information.
- **UIX8C-R053** — Animations remain lightweight, bounded, and do not block
  cashier interaction.
- **UIX8C-R054** — Dynamic colour must not override the governed Aish brand
  identity unless explicitly approved via ADR.
- **UIX8C-R055** — Phone portrait is the mandatory baseline; tablet adaptation
  must not regress it.

### Evidence & sprint-tag discipline
- **UIX8C-R056** — Component previews, screenshot tests, and emulator validation
  are development evidence and never replace physical-device runtime closure.
- **UIX8C-R057** — Design-system regression is a release blocker (the fail-closed
  `scripts/uix8c_design_system_gate.sh` enforces tokens, components, no-hardcode,
  touch targets, font-scale test presence, and status-not-colour-alone).
- **UIX8C-R058** — Old failed physical evidence (`run-97fbb64-2af94aa`) remains
  immutable after visual remediation; R18 is not marked PASS by any automated or
  emulator evidence.
- **UIX8C-R059** — Any Android runtime visual change requires a new final APK and
  new physical evidence after code freeze.
- **UIX8C-R060** — A sprint-scoped implementation GO tag (UIX8C-R002) never
  implies UIX-7 or UIX-8 runtime GO. UIX-7 stays `NO-GO — GO DEFERRED` and UIX-8
  stays `IMPLEMENTATION COMPLETE — GO DEFERRED` until genuinely closed against the
  UIX-7/UIX-8 GO discipline, regardless of any `uix-8c-NN-*-go` sprint tag.

## UIX-8C-03 — Premium cashier home, product catalog, search, category & cart
Introduced by UIX-8C-03 (premium rebuild of the cashier home, canonical context
header, product catalog + truthful states, product search, category filter, and
the deterministic integer-exact cart). These rules are the permanent
cashier-experience baseline for every subsequent Android sprint and extend —
never weaken — rules 55/56/57/58/59 and UIX8C-R001..R060.

### Cashier home & authorization
- **UIX8C-R061** — Cashier Home is the canonical operational surface for Android
  cashier users.
- **UIX8C-R062** — Cashier Home must display canonical tenant, outlet, cashier,
  device, and connectivity context (sourced from authenticated `auth/me`; a
  missing value renders "Tidak tersedia", and online is claimed only when
  identity resolved from the server this session — online ≠ merely connected).
- **UIX8C-R063** — Android UI must never trust raw client-supplied tenant or
  outlet identifiers for authorization.
- **UIX8C-R064** — Cashier UI must never expose platform-admin or owner controls.

### Product catalog & truthful states
- **UIX8C-R065** — Product catalog data remains tenant and outlet scoped.
- **UIX8C-R066** — Product loading, loaded, empty, no-result, unavailable,
  offline-cached, and error states remain distinct.
- **UIX8C-R067** — Loading state must never be displayed as an empty catalog.
- **UIX8C-R068** — Search no-result must never be displayed as an empty catalog.
- **UIX8C-R069** — Backend error must not silently erase an existing valid cart.
- **UIX8C-R070** — Offline cached catalog must be labelled truthfully.
- **UIX8C-R071** — Product stock and availability status must be textual and not
  color-only.
- **UIX8C-R072** — An unavailable or out-of-stock product cannot be added to the
  cart.
- **UIX8C-R073** — Product price uses the canonical whole-Rupiah formatter.

### Search & category filter
- **UIX8C-R074** — Search and category filtering must not mutate cart content.
- **UIX8C-R075** — Clearing search or category filters restores the canonical
  catalog state.

### Cart integrity
- **UIX8C-R076** — Cart add, increment, decrement, remove, and clear operations
  remain deterministic.
- **UIX8C-R077** — Cart quantities must never become zero or negative inside a
  valid cart row.
- **UIX8C-R078** — Cart totals use canonical whole-Rupiah integer calculations.
- **UIX8C-R079** — UI must not duplicate pricing, discount, tax, stock,
  entitlement, or payment business rules.
- **UIX8C-R080** — Clear-cart is destructive and requires explicit confirmation.
- **UIX8C-R081** — Cancelling clear-cart preserves the cart unchanged.
- **UIX8C-R082** — Configuration changes must preserve valid cart state.
- **UIX8C-R083** — Expected process recreation must restore valid cart state
  where supported.
- **UIX8C-R084** — API loading or error transitions must preserve a valid
  existing cart.
- **UIX8C-R085** — Checkout CTA is disabled or unavailable when the cart is empty
  or invalid.

### Responsive, font-scale & layout integrity
- **UIX8C-R086** — Checkout CTA remains visible or scroll-reachable at 130% font
  scale.
- **UIX8C-R087** — Product catalog remains visible or scroll-reachable at 130%
  font scale.
- **UIX8C-R088** — Cart, totals, and quantity controls remain visible or
  scroll-reachable at 130% font scale.
- **UIX8C-R089** — Product, category, tenant, outlet, and cashier long names wrap
  or ellipsize safely.
- **UIX8C-R090** — Product and cart interactive targets remain at least 48dp.

### Accessibility & performance
- **UIX8C-R091** — Product, quantity, remove, search, category, and checkout
  controls have meaningful accessibility semantics.
- **UIX8C-R092** — Focus order follows context, search, categories, products,
  cart, totals, and checkout.
- **UIX8C-R093** — Cashier screen rendering must remain lightweight and avoid
  unbounded layout work.

### Release discipline
- **UIX8C-R094** — Cashier catalog/cart regression is a sprint release blocker.
- **UIX8C-R095** — UIX-8C-03 implementation GO does not imply UIX-7/UIX-8 runtime
  GO. UIX-7 stays `NO-GO — GO DEFERRED` and UIX-8 stays `IMPLEMENTATION COMPLETE
  — GO DEFERRED`; R11 offline CASH durability stays UNRESOLVED / out of scope.

## UIX-8C-04 — Offline CASH durability & idempotent recovery
Introduced by UIX-8C-04 (the P1 financial-integrity fix for the physical R11
failure: an eligible online CASH checkout that cannot reach the backend must
degrade to a durable local transaction instead of a hard failure that persists
nothing). These rules are the permanent offline-durability / transport-safety /
idempotency baseline for every subsequent Android sprint and extend — never
weaken — rules 55/56/57/58/59 and UIX8C-R001..R095. Business truth stays in the
backend `App\Services\*` domains and the app's canonical repositories; the
cashier persists and replays only — never a second pricing/payment/QRIS/
settlement/sync engine.

### Transport classification & offline eligibility
- **UIX8C-R096** — Offline payment capability is CASH-only; QRIS and every
  server-confirmed payment method remain online-only.
- **UIX8C-R097** — Every logical checkout owns one stable `clientReference`
  minted once and reused across the online attempt, the local fallback, process
  restart, reconnect, and worker replay.
- **UIX8C-R098** — Eligible transport and temporary-unavailability failures may
  trigger governed offline CASH fallback.
- **UIX8C-R099** — HTTP 400 validation failure must never be converted into
  offline success.
- **UIX8C-R100** — HTTP 401 authentication failure must never be converted into
  offline success.
- **UIX8C-R101** — HTTP 403 authorization, entitlement, tenant, outlet, cashier,
  or device rejection must never be converted into offline success.
- **UIX8C-R102** — HTTP 409 and other canonical conflicts follow explicit domain
  policy and must not silently queue as a new offline transaction.
- **UIX8C-R103** — Unknown programming, mapping, serialization, or
  data-integrity errors must not silently enter offline fallback; TLS
  certificate/hostname/trust validation failure is a security error and is never
  an offline condition.
- **UIX8C-R104** — Offline CASH fallback requires complete canonical tenant,
  outlet, cashier, device, cart, tender, and money context.

### Durable local persistence & cart-clear semantics
- **UIX8C-R105** — An offline transaction is successful to the operator only
  after one durable local database commit.
- **UIX8C-R106** — The local offline save is atomic across transaction header,
  items, payment metadata, totals, `clientReference`, and sync state.
- **UIX8C-R107** — Cart clearing occurs only after canonical online
  acknowledgement or a successful durable local save.
- **UIX8C-R108** — Local persistence failure preserves the complete cart and
  presents a truthful failure.
- **UIX8C-R109** — Repeated taps or repeated fallback handling create at most one
  local transaction for the same `clientReference`.

### Sync state & recovery
- **UIX8C-R110** — Local transaction states PENDING, SYNCING, SYNCED, FAILED, and
  CONFLICT remain distinct.
- **UIX8C-R111** — A local transaction becomes SYNCED only after canonical server
  acknowledgement and durable recording of the server result.
- **UIX8C-R112** — Process death after local commit must not lose the
  transaction.
- **UIX8C-R113** — Application restart restores the same pending transaction and
  `clientReference`.
- **UIX8C-R114** — Device reconnect reuses the existing local transaction instead
  of creating a new logical checkout.
- **UIX8C-R115** — WorkManager retry is bounded and follows governed network
  constraints and backoff.
- **UIX8C-R116** — Worker replay is idempotent and cannot duplicate a sale,
  payment, sale item, or inventory movement.
- **UIX8C-R117** — Orphan SYNCING rows recover to a safe retryable state without
  fabricating server acknowledgement.

### Backend idempotency & financial integrity
- **UIX8C-R118** — A backend replay of the same tenant-scoped `clientReference`
  returns or reconciles the canonical transaction without a second financial
  mutation.
- **UIX8C-R119** — One logical checkout produces exactly one canonical sale.
- **UIX8C-R120** — One logical checkout produces exactly one canonical payment.
- **UIX8C-R121** — Sale items, quantities, unit prices, and whole-Rupiah totals
  remain exact across offline persistence and sync.
- **UIX8C-R122** — Inventory side effects remain idempotent and cannot duplicate
  during replay.
- **UIX8C-R123** — Printer, receipt rendering, analytics, or UI presentation
  failures do not alter financial authority.

### Truthful state, safety & evidence
- **UIX8C-R124** — Offline queued UI states are truthful and must not claim server
  synchronization.
- **UIX8C-R125** — A stale previous success or receipt must never be displayed for
  the current offline checkout.
- **UIX8C-R126** — Logout, account switch, or device switch must not silently
  discard unsynced transactions.
- **UIX8C-R127** — Runtime logs, exceptions, and evidence must not expose
  credentials, tokens, customer PII, or payment secrets.
- **UIX8C-R128** — Offline durability, duplicate transaction, payment
  duplication, inventory duplication, or transaction loss is an automatic release
  NO-GO.
- **UIX8C-R129** — Source remediation does not rewrite the historical failed
  physical R11 evidence; a new physical campaign is mandatory after final code
  freeze.
- **UIX8C-R130** — UIX-8C-04 implementation GO confirms source remediation and
  automated verification only; it does not imply UIX-7 or UIX-8 runtime GO.

## Scope guard for UIX-8C-01
- UIX-8C-01 does not fix R11 (offline CASH durability), does not perform a broad
  runtime visual rebuild, does not modify runtime evidence/manifest state, does
  not run a physical campaign, does not build a closure APK, and does not create
  any GO tag. It establishes governance, architecture, inventory, and the
  fail-closed foundation gate only.

## Scope guard for UIX-8C-02
- UIX-8C-02 hardens the design system, builds the reusable component library, and
  fixes the structural R18 large-font layout risk on the cashier and payment
  surfaces. It does NOT fix R11 (offline CASH durability), does NOT change
  `SaleService`/backend financial behaviour, does NOT alter Room offline
  transaction semantics or canonical runtime-evidence results, does NOT run a
  physical campaign, and does NOT create a UIX-7 or UIX-8 GO tag. It MAY create
  the sprint-scoped tag `uix-8c-02-premium-design-system-hardening-go` under
  UIX8C-R002. Full per-screen rebuilds continue in UIX-8C-03..09.

## Scope guard for UIX-8C-03
- UIX-8C-03 rebuilds the premium cashier home, canonical context header, product
  catalog + truthful states, product search, category filter, and the
  deterministic integer-exact cart (UIX8C-R061..R095). It does NOT fix R11
  (offline CASH durability), does NOT change `SaleService`/backend financial
  behaviour, does NOT alter Room offline transaction semantics, payment
  idempotency, or canonical runtime-evidence results, does NOT rebuild
  payment/receipt/history, does NOT run a physical campaign, and does NOT create
  a UIX-7 or UIX-8 GO tag. Checkout changes are limited to a safe handoff to the
  existing governed payment sheet. It MAY create the sprint-scoped tag
  `uix-8c-03-premium-cashier-catalog-cart-go` under UIX8C-R002. Full per-screen
  rebuilds continue in UIX-8C-04..09.

## Scope guard for UIX-8C-04
- UIX-8C-04 fixes the P1 offline CASH durability defect behind the physical R11
  failure (UIX8C-R096..R130): a typed transport classifier, a governed
  online→offline CASH fallback, atomic durable Room persistence, a stable
  `clientReference` reused across the online attempt/fallback/restart/reconnect/
  worker replay, cart-clear-only-after-durability, bounded WorkManager retry,
  orphan-SYNCING recovery, and backend idempotency regression coverage. It does
  NOT change `SaleService`/backend financial behaviour beyond adding tests (the
  backend already dedupes), does NOT rebuild the premium payment/receipt/history
  screens (only the truthful offline-queued state), does NOT enable QRIS offline,
  does NOT run a physical campaign, does NOT flip the historical R11 evidence to
  PASS, and does NOT create a UIX-7 or UIX-8 GO tag. It MAY create the
  sprint-scoped tag `uix-8c-04-offline-cash-durability-idempotent-recovery-go`
  under UIX8C-R002; that tag confirms source remediation + automated verification
  only and never asserts UIX-7/UIX-8 runtime closure (UIX8C-R130). A fresh
  physical-device revalidation of R11 remains mandatory after final code freeze
  (UIX8C-R129). Premium payment UX continues in UIX-8C-05.

## ADR requirement
A material change to the delivery-train architecture, navigation graph, screen
state architecture, component architecture, adaptive layout, receipt/payment
state machine, or accessibility strategy requires an ADR under `docs/adr/`.
UIX-8C-01 is recorded by
`docs/adr/0004-uix-8c-full-premium-rebuild.md`; the UIX-8C-02 design-system
hardening, responsive cashier shell, and sprint-tag governance refinement are
recorded by `docs/adr/0005-uix-8c-02-premium-design-system-hardening.md`. The
UIX-8C-04 offline CASH durability & idempotent-recovery remediation is recorded
by `docs/adr/0006-uix-8c-04-offline-cash-durability.md`.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists
  and that `UIX8C-R001..R130` are persisted.
- `scripts/uix8c_offline_cash_durability_gate.sh` (fail-closed) enforces the
  UIX-8C-04 offline CASH durability baseline (UIX8C-R096..R130): rule
  persistence, the root-cause/architecture/test-matrix/threat-model docs, the
  typed transport classifier, the prohibition of a broad catch-all offline
  fallback, QRIS-offline prohibition, the atomic Room transaction path, the
  cart-clear-after-durable-save contract, the stable-`clientReference` path,
  bounded WorkManager retry, orphan-SYNCING recovery, the Android + backend
  idempotency tests, no floating-point money on the offline path, the immutable
  failed physical run, UIX-7/UIX-8 deferred status, no premature closure tag, and
  the sprint-tag semantics. Its self-tests are
  `scripts/tests/uix8c_offline_cash_durability_gate_test.sh`.
- `scripts/uix8c_cashier_catalog_cart_gate.sh` (fail-closed) enforces the
  UIX-8C-03 cashier/catalog/cart baseline (UIX8C-R061..R095): rule persistence,
  the canonical context component + include, the category filter + adapter, the
  truthful catalog states, the whole-Rupiah formatter, the clear-cart
  confirmation, search-clear + retry affordances, ≥48dp touch targets,
  font-scale/accessibility test presence, status-not-colour-alone, no premature
  UIX-7/UIX-8 GO, immutable failed physical evidence, and the sprint-tag
  semantics. Its self-tests are
  `scripts/tests/uix8c_cashier_catalog_cart_gate_test.sh`.
- `scripts/uix8c_foundation_gate.sh` (fail-closed) validates rule persistence,
  the screen inventory, the state/accessibility matrix, the ADR, the immutable
  failed physical run record, UIX-7/UIX-8 deferred status, the umbrella/final
  GO-tag absence (while permitting sprint-scoped `uix-8c-NN-*-go` tags per
  UIX8C-R002), and the no-secret / no-premature-GO invariants. Its self-tests are
  `scripts/tests/uix8c_foundation_gate_test.sh`.
- `scripts/uix8c_design_system_gate.sh` (fail-closed) enforces the UIX-8C-02
  visual/responsive/accessibility baseline (UIX8C-R031..R060): centralized
  tokens, canonical components, no hardcoded off-system values in changed UI,
  48dp touch targets, font-scale test presence, and status-not-colour-alone. Its
  self-tests are `scripts/tests/uix8c_design_system_gate_test.sh`.
- All are wired into the authoritative CI foundation lane
  (`.github/workflows/_foundation-gates.yml`).
- Because `main` is not branch-protected, GO discipline is enforced by rule and
  reviewer discipline; do not tag until every gate is genuinely met.
