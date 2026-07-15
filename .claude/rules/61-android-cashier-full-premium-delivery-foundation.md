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

## UIX-8C-05 — Premium cash payment, offline queue & sync recovery UX
Introduced by UIX-8C-05 (the premium CASH payment sheet and the truthful
offline-queue / sync-recovery experience) on top of the UIX-8C-04 transaction
foundation. These rules are the permanent payment-presentation /
sync-recovery-UX / manual-retry baseline for every subsequent Android sprint and
extend — never weaken — rules 55/56/57/58/59 and UIX8C-R001..R130. UIX-8C-05
reuses the UIX-8C-04 transport classifier, durable persistence, stable
`clientReference`, WorkManager, and backend idempotency; it presents and
orchestrates only and is never a second checkout, offline, sync, or backend sale
engine.

### Presentation boundary & foundation reuse
- **UIX8C-R131** — The payment sheet is a presentation surface and must delegate
  financial and transaction authority to the canonical ViewModel, repository, and
  domain flow.
- **UIX8C-R132** — UIX-8C-05 must reuse the UIX-8C-04 transport classifier,
  durable offline persistence, stable `clientReference`, WorkManager, and backend
  idempotency foundation.
- **UIX8C-R133** — UIX-8C-05 must not introduce a second checkout, offline
  persistence, sync, or backend sale path.
- **UIX8C-R134** — Offline payment capability remains CASH-only; QRIS and every
  server-confirmed payment method remain online-only.

### Money, tender, quick tender & change
- **UIX8C-R135** — Amount due comes from the canonical cart snapshot and
  whole-Rupiah calculation.
- **UIX8C-R136** — Tender and change use whole-Rupiah integer types and never
  floating-point financial values.
- **UIX8C-R137** — Manual tender parsing is locale-safe, overflow-safe,
  deterministic, and rejects malformed values.
- **UIX8C-R138** — Quick-tender options are derived from or validated against the
  canonical amount due.
- **UIX8C-R139** — Insufficient tender cannot enter the checkout submission path.
- **UIX8C-R140** — Change equals tender minus canonical amount due and must never
  be negative.

### Submit guard & durable success semantics
- **UIX8C-R141** — One visible payment interaction owns at most one active logical
  checkout attempt.
- **UIX8C-R142** — Rapid taps, repeated callbacks, rotation, or view recreation
  must not submit a second logical transaction.
- **UIX8C-R143** — Submit controls remain guarded while the canonical checkout
  attempt is active.
- **UIX8C-R144** — Online success is displayed only after canonical server
  acknowledgement.
- **UIX8C-R145** — Offline queued success is displayed only after durable local
  database commit.

### Truthful state machine
- **UIX8C-R146** — Offline queued, PENDING, SYNCING, RETRYING, FAILED, CONFLICT,
  SYNCED, and online-success states remain semantically distinct.
- **UIX8C-R147** — Offline queued or PENDING UI must never claim server
  synchronization or payment settlement.
- **UIX8C-R148** — SYNCED is displayed only after durable canonical acknowledgement
  is recorded locally.
- **UIX8C-R149** — Canonical HTTP, authentication, authorization, entitlement,
  tenant, outlet, device, validation, and TLS rejection remain explicit failures
  and must not appear as offline queued success.

### Foundation-reuse invariants
- **UIX8C-R150** — UIX-8C-05 must reuse the UIX-8C-04 typed transport classifier
  without broad catch-all fallback.
- **UIX8C-R151** — UIX-8C-05 must reuse the UIX-8C-04 stable `clientReference`
  without regeneration during retry, rotation, restart, or reconnect.
- **UIX8C-R152** — UIX-8C-05 must reuse the UIX-8C-04 atomic Room persistence and
  must not clear the cart independently.
- **UIX8C-R153** — Dismissing or recreating the payment sheet must not cancel,
  duplicate, or mutate an already durable transaction.

### Process restoration, reconnect & manual retry
- **UIX8C-R154** — Process restoration derives durable transaction truth from Room
  and canonical repositories, not stale in-memory UI events.
- **UIX8C-R155** — A previous success, receipt, tender, or change result must never
  be replayed for a new checkout.
- **UIX8C-R156** — Reconnect feedback is informative and must not create duplicate
  sync work.
- **UIX8C-R157** — Safe manual retry reuses the existing transaction and
  `clientReference` and does not create a new logical checkout.
- **UIX8C-R158** — Manual retry must respect canonical state, idempotency, network
  constraints, worker coordination, and bounded retry policy.
- **UIX8C-R159** — Manual retry must not run concurrently with an active worker for
  the same transaction without governed coordination.
- **UIX8C-R160** — Conflict state is explicit, preserves evidence, and must not be
  silently converted to success or a new transaction.

### Accessibility, layout & safety
- **UIX8C-R161** — Payment errors expose truthful, actionable, accessible text
  without leaking secrets or internal exception payloads.
- **UIX8C-R162** — Payment, status, retry, dismiss, and confirmation controls
  remain at least 48dp.
- **UIX8C-R163** — TalkBack semantics identify amount due, tender, change, payment
  action, offline queued state, synchronization state, retry action, and conflict
  state.
- **UIX8C-R164** — Payment focus order follows amount due, quick tender, manual
  tender, validation, change, cancel, and confirm.
- **UIX8C-R165** — Payment and sync status must not rely on color alone.
- **UIX8C-R166** — Payment sheet, validation, queued state, retry action, and
  critical status remain usable at 100%, 115%, and 130% font scale.
- **UIX8C-R167** — Long Rupiah values, translated labels, error messages, and
  status text wrap or scroll safely without hiding critical actions.
- **UIX8C-R168** — Printer, receipt rendering, analytics, animation, or
  presentation failures do not change transaction authority.

### Evidence & closure discipline
- **UIX8C-R169** — UIX-8C-05 source remediation and automated validation do not
  rewrite historical R11 evidence; fresh physical R11 revalidation remains
  mandatory after final code freeze.
- **UIX8C-R170** — UIX-8C-05 implementation GO does not imply UIX-7 or UIX-8
  runtime GO.

## UIX-8C-06 — Premium receipt, transaction history & printer failure states
Introduced by UIX-8C-06 (the premium receipt / transaction-detail surface, the
reconciled transaction history, and the typed printer failure-state experience)
on top of the UIX-8C-04/05 transaction foundation. These rules are the permanent
receipt-projection / history-reconciliation / printer-reliability baseline for
every subsequent Android sprint and extend — never weaken — rules 55/56/57/58/59
and UIX8C-R001..R170. UIX-8C-06 reuses the stable `clientReference`, the canonical
sale/payment result, the durable offline Room transaction, the PENDING/SYNCING/
SYNCED/FAILED/CONFLICT states, backend sale/payment idempotency, whole-Rupiah
money, and `PaymentUiState`; it presents and projects only and introduces no
second transaction path.

### Receipt binding & parity
- **UIX8C-R171** — Receipt and transaction-history surfaces are projections of
  canonical transaction state and must not create or mutate financial
  transactions.
- **UIX8C-R172** — Every receipt is bound to one current logical transaction
  through its stable clientReference and governed local or server identifiers.
- **UIX8C-R173** — A receipt from a previous transaction must never replace or
  appear as the result of the current checkout.
- **UIX8C-R174** — Online receipt success is shown only after canonical server
  acknowledgement.
- **UIX8C-R175** — Offline receipt success is shown only after durable local
  commit and must be labelled PENDING or offline-queued.
- **UIX8C-R176** — A receipt is labelled SYNCED only after durable canonical
  acknowledgement is recorded locally.
- **UIX8C-R177** — Receipt items, quantities, unit prices, line totals, subtotal,
  total, tender, and change must match the canonical transaction exactly.
- **UIX8C-R178** — Receipt, transaction history, local persistence, server sale,
  and server payment parity are mandatory release invariants.
- **UIX8C-R179** — Receipt and history money use canonical whole-Rupiah integer
  values and must never be recalculated with floating-point types.
- **UIX8C-R180** — Receipt references exposed to the operator use governed
  transaction identifiers and must not reveal secrets or unsafe internal
  identifiers.

### History reconciliation & deduplication
- **UIX8C-R181** — One logical transaction produces at most one transaction-history
  entry after local and server records are reconciled.
- **UIX8C-R182** — Local pending and server-confirmed records for the same
  clientReference must merge rather than appear as duplicate history entries.
- **UIX8C-R183** — Transaction history remains scoped by canonical tenant, outlet,
  cashier, and device rules.
- **UIX8C-R184** — History states PENDING, SYNCING, RETRYING, SYNCED, FAILED, and
  CONFLICT remain semantically distinct.
- **UIX8C-R185** — History loading, empty, no-result, unavailable, and error
  states remain distinct.
- **UIX8C-R186** — Refresh, reconnect, worker acknowledgement, and process
  restoration must not duplicate or reorder one logical transaction incorrectly.

### Process restoration & stale-result prevention
- **UIX8C-R187** — Process restoration derives receipt and history truth from Room
  and canonical repositories rather than stale in-memory events.
- **UIX8C-R188** — Starting a new transaction clears only obsolete presentation
  state and must not delete or mutate durable receipt/history records.
- **UIX8C-R189** — Reopening a receipt uses persisted canonical transaction data
  and must not reconstruct financial values from mutable cart state.
- **UIX8C-R190** — One-shot receipt, navigation, print, and success events must not
  replay into a different transaction after rotation or process recreation.

### Printer non-financial authority
- **UIX8C-R191** — Printer availability, permission, connection, print completion,
  or print failure never determines financial transaction success.
- **UIX8C-R192** — Print failure must not roll back, duplicate, cancel, or resubmit
  a sale or payment.
- **UIX8C-R193** — Reprint is a presentation operation and must not create a new
  transaction, clientReference, payment, sale item, or inventory movement.

### Printer permissions & typed states
- **UIX8C-R194** — Printer permission requests follow least privilege and the
  actual printer transport in use.
- **UIX8C-R195** — BLUETOOTH_SCAN or discovery-related permission must not be
  introduced unless the application actually performs device discovery.
- **UIX8C-R196** — Connection to an already paired Bluetooth printer must use the
  minimum platform permissions required by the supported Android version.
- **UIX8C-R197** — Permission denied, unsupported hardware, adapter disabled,
  device unavailable, connection failure, timeout, write failure, and interrupted
  print are typed and distinct printer states.
- **UIX8C-R198** — Printer retry is bounded, reuses the same receipt projection,
  and cannot trigger checkout or transaction replay.
- **UIX8C-R199** — Printer callbacks, exceptions, logs, and evidence must not
  expose tokens, customer PII, payment secrets, or full sensitive transaction
  payloads.
- **UIX8C-R200** — Print jobs must not block the Android main thread or create
  unbounded connection, retry, or write loops.
- **UIX8C-R201** — Receipt and print content include only governed business,
  outlet, cashier, transaction, item, and payment fields.

### Accessibility, layout & safety
- **UIX8C-R202** — Receipt, history, print, retry, and navigation controls remain
  at least 48dp.
- **UIX8C-R203** — TalkBack semantics identify transaction state, item details,
  totals, tender, change, history row, receipt action, print action, printer
  state, and retry action.
- **UIX8C-R204** — Receipt and history focus order follows transaction context,
  items, totals, payment details, state, print, and next action.
- **UIX8C-R205** — Receipt, history, and printer states must not rely on color
  alone.
- **UIX8C-R206** — Receipt, transaction history, detail, printer failure, and
  critical actions remain usable at 100%, 115%, and 130% system font scale.
- **UIX8C-R207** — Long business, outlet, cashier, product, reference, status, and
  error text wrap, ellipsize, or scroll safely without hiding critical actions.
- **UIX8C-R208** — Receipt rendering, history merging, and printer operations
  remain lifecycle-aware, bounded, and free from main-thread network or database
  work.

### Evidence & closure discipline
- **UIX8C-R209** — UIX-8C-06 source and automated validation do not rewrite
  historical physical evidence; final physical receipt/history/printer validation
  remains mandatory after code freeze.
- **UIX8C-R210** — UIX-8C-06 implementation GO does not imply UIX-7 or UIX-8
  runtime GO.

## UIX-8C-07 — Premium authentication, device activation, settings & session recovery
Introduced by UIX-8C-07 (the premium startup/authentication/device-activation
experience, the operational Settings surface, and safe session/process recovery)
on top of the UIX-8C-04/05/06 transaction foundation. These rules are the
permanent startup-state-machine / device-trust / runtime-context / tenant-isolation
/ session-recovery / settings-truthfulness baseline for every subsequent Android
sprint and extend — never weaken — rules 55/56/57/58/59 and UIX8C-R001..R210.
UIX-8C-07 REUSES the stable `clientReference`, `OfflineSaleRepository` durable
persistence, `OfflineSyncStatus`, `PaymentUiState`, receipt/history projections,
backend Sanctum auth and the Sprint-34 device-activation/revocation domain; it
presents and orchestrates only and introduces no second checkout, offline, sync,
pricing, payment, or backend-sale engine.

### Deterministic startup & authentication state machine
- **UIX8C-R211** — App startup and authentication MUST be governed by a single
  deterministic state machine (`core/startup/BootState` + pure
  `core/startup/StartupCoordinator`); navigation decisions MUST NOT be scattered
  across activities, fragments, interceptors, or workers in conflicting ways.
- **UIX8C-R212** — The app MUST enter a `Ready` state only when installation
  identity, device activation, non-revoked device, tenant binding, outlet
  binding, a valid session (or a policy-permitted offline continuation), cashier
  authorization for the tenant/outlet, and a matching local data partition are
  ALL valid.
- **UIX8C-R213** — The startup/auth states (Bootstrapping, DatabaseMigration,
  RestoringRuntime, ActivationRequired, ActivatingDevice, LoginRequired,
  Authenticating, Ready, OfflineReady, SessionExpired, DeviceInvalid,
  DeviceRevoked, ContextMismatch, RecoveryRequired, RecoverableFailure,
  FatalFailure) MUST be explicit and their transitions deterministic and tested.
- **UIX8C-R214** — A connectivity signal MUST NOT be treated as guaranteed server
  reachability or as proof of session/device validity.
- **UIX8C-R215** — Startup MUST be bounded (timeout) with a recoverable error
  path; it MUST NOT trap on an infinite splash or a crash loop.
- **UIX8C-R216** — Startup MUST NOT flash the login screen when a valid session is
  restorable.

### Device trust foundation
- **UIX8C-R217** — Device activation and cashier authentication are two DISTINCT
  trust gates; satisfying one MUST NOT satisfy the other.
- **UIX8C-R218** — Device installation identity MUST be an application-generated
  identifier stored via Android Keystore-backed secure storage; IMEI, hardware
  serial, MAC address, and other invasive hardware identifiers MUST NOT be used.
- **UIX8C-R219** — The authentication token and device credentials MUST use
  Android Keystore-backed secure storage (never plaintext preferences) and MUST
  NOT be logged, screenshotted, or placed in analytics/crash payloads.
- **UIX8C-R220** — A revoked or invalid device MUST fail closed: no tenant data
  render, no sync mutation, no new print job, no new transaction; the block MUST
  NOT be bypassable via back navigation, deep link, process restart, or offline
  mode.
- **UIX8C-R221** — Device validity is server-authoritative; the app MUST poll a
  truthful device-status contract (active / revoked + reason) and MUST NOT
  self-assert device validity from local cache.

### Runtime context source of truth
- **UIX8C-R222** — There MUST be one runtime-context source of truth
  (`core/runtime/RuntimeContext`: tenant, outlet, cashier, device, session,
  installation, application build); every repository, query, sync job,
  transaction write, receipt projection, printer operation, and navigation
  decision MUST derive its context from it.
- **UIX8C-R223** — Tenant, outlet, cashier, and device context MUST come from
  authenticated state, never from client-supplied UI input as authority.
- **UIX8C-R224** — Cross-tenant cached identity is forbidden; account or device
  switching MUST clear or re-scope tenant-scoped local data before another tenant
  is usable.
- **UIX8C-R225** — Runtime context MUST be validated before `Ready`; an
  unvalidated raw cache string MUST NOT be trusted as authoritative context.

### Tenant isolation fail-closed
- **UIX8C-R226** — On any tenant mismatch (token, device binding, local cache,
  outlet, cashier, request, database row, or restored state) the app MUST fail
  closed: block access, render no data, run no sync, print nothing, open no
  transaction, and MUST NOT silently fall back to the last tenant — it enters an
  explicit recovery state.
- **UIX8C-R227** — A tenant mismatch MUST produce an audit-safe diagnostic
  containing no credentials, tokens, PII, or payment secrets.
- **UIX8C-R228** — An automated tenant-isolation test MUST write Tenant A data,
  perform a valid switch/reset, and prove Tenant B cannot read Tenant A artifacts.

### Unsynced-transaction safety
- **UIX8C-R229** — Logout, cashier switch, outlet switch, tenant switch,
  activation reset, and cache cleanup MUST NOT delete unsynced transactions.
- **UIX8C-R230** — Normal logout and account switch MUST be blocked while unsynced
  transactions exist, unless a governed, tested recovery policy explicitly
  applies.
- **UIX8C-R231** — The unsynced gate MUST count every transaction lacking a valid
  server acknowledgement (not only items visible in the UI); `OFFLINE_PENDING`
  stays `OFFLINE_PENDING` until a valid ACK (UIX-8C-04/06 semantics unchanged).
- **UIX8C-R232** — A blocked logout/switch MUST surface the pending count, the
  reason, a "Sync sekarang" action, sync status, a safe retry, a path back to the
  transactions, and a recovery message when sync is impossible.
- **UIX8C-R233** — On session expiry the app MUST lock the UI, preserve the
  same-tenant pending transactions, require re-authentication, and resume only
  after a valid login with the same tenant/outlet.
- **UIX8C-R234** — On a revoked or invalid device the app MUST fail closed,
  protect the pending queue in a tenant/device-bound quarantine, never move a
  transaction to another tenant, and show a safe support reference.

### Cross-tenant cache hygiene
- **UIX8C-R235** — A same-tenant/outlet cashier switch and a cross-tenant
  reactivation are distinct operations and MUST be handled distinctly.
- **UIX8C-R236** — Before another tenant can be used the app MUST: confirm no
  unsynced transaction, stop old-context workers, revoke/clear old credentials,
  close the old database handle, clear tenant/outlet/cashier-scoped cache, files,
  printer job, search/history and restored navigation state, build and validate
  the new context, and only then open the new database/context.
- **UIX8C-R237** — Cleanup MUST be transactional or carry a safe compensating
  recovery.

### Process & session restoration
- **UIX8C-R238** — Process death MUST NOT cause a duplicate sale, duplicate sync,
  duplicate receipt, double payment, wrong tenant/cashier context, an unsafe
  reopening of a half-finished payment, an endless startup spinner, or a crash
  loop.
- **UIX8C-R239** — Only safe, re-validated state MAY be restored (device
  activation, tenant/outlet binding, valid cashier session, pending transaction
  queue, last safe navigation destination, settings context); raw credential
  input, activation token, half-submitted payment mutation, stale printer success,
  and stale connected status MUST NOT be restored.
- **UIX8C-R240** — Operations repeatable after process death MUST be idempotent
  via the stable UIX-8C-06 `clientReference`/receipt identity; no parallel
  transaction or receipt identity is created.
- **UIX8C-R241** — Restoration MUST derive truth from Room and canonical
  repositories, not from stale in-memory UI events.

### Truthful status
- **UIX8C-R242** — Connection, sync, printer, activation, and session status MUST
  derive from the actual source of truth; a status MUST NOT read as green merely
  because a configuration is saved.
- **UIX8C-R243** — Status MUST distinguish Configured, Checking, Connected,
  Disconnected, PermissionRequired, Unavailable, Degraded, SyncPending, Syncing,
  SyncFailed, SessionExpired, and DeviceRevoked.
- **UIX8C-R244** — Status MUST NOT rely on colour alone; a text label always
  accompanies colour-coded state.

### Premium settings surface
- **UIX8C-R245** — Settings MUST present premium operational settings with
  Account/Context, Device, Application, Connection, Sync, Printer, and
  Security/Session sections; values are truthful and render "Tidak tersedia" when
  unknown.
- **UIX8C-R246** — Settings MUST NOT render the auth token, secrets, the raw
  activation code, or a raw encryption identifier; device/installation identifiers
  are shortened.
- **UIX8C-R247** — Settings MUST reuse canonical repositories/status and the
  existing printer subsystem; it is not a second engine and introduces no parallel
  printer/sync/session engine.

### Accessibility, evidence & closure discipline
- **UIX8C-R248** — All authentication, activation, startup, settings, dialog,
  error, and recovery surfaces MUST remain usable at 100%, 115%, and 130% system
  font scale (no clipped CTA, no critical-text truncation, scroll where space is
  short), with ≥48dp touch targets, meaningful TalkBack labels, logical focus
  order, announced errors, and never colour-alone status.
- **UIX8C-R249** — Emulator and automated evidence MUST stay labelled as such;
  operator-observed accessibility and runtime PASS requires genuine human
  observation and is never fabricated; a sprint-scoped GO tag never asserts UIX-7
  or UIX-8 runtime closure.
- **UIX8C-R250** — Authentication, device, session, settings, tenant-isolation,
  unsynced-protection, or process-restoration regression is a sprint release
  blocker. UIX-7 stays `NO-GO — GO DEFERRED` and UIX-8 stays `IMPLEMENTATION
  COMPLETE — GO DEFERRED` until genuinely closed against the UIX-7/UIX-8 GO
  discipline, regardless of any `uix-8c-NN-*-go` sprint tag.

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

## Scope guard for UIX-8C-05
- UIX-8C-05 builds the premium CASH payment sheet and the truthful offline-queue
  / sync-recovery experience (UIX8C-R131..R170): a whole-Rupiah tender/quick-tender/
  validation/change presentation, a ViewModel-level double-submit guard, a truthful
  payment/sync presentation state machine (a durable save projects to OfflineQueued,
  never Synced; SYNCED only on a recorded server ack), process-restoration truth
  from Room, informative reconnect feedback, and a SAFE governed manual retry. It
  REUSES the UIX-8C-04 `TransportFailureClassifier`, `OfflineSaleRepository` durable
  persistence, stable `clientReference`, `OfflineSalesSyncScheduler`/WorkManager,
  and backend idempotency — it introduces NO second checkout, offline persistence,
  transport classifier, sync pipeline, or backend sale path. It does NOT change
  `SaleService`/backend financial behaviour beyond adding a regression fence (the
  backend already dedupes), does NOT rebuild the full receipt/history screens, does
  NOT enable QRIS offline, does NOT convert a canonical/TLS rejection into offline
  success, does NOT run a physical campaign, does NOT flip the historical R11
  evidence to PASS, and does NOT create a UIX-7 or UIX-8 GO tag. It MAY create the
  sprint-scoped tag `uix-8c-05-premium-cash-payment-offline-sync-recovery-go` under
  UIX8C-R002; that tag confirms source remediation + automated verification only and
  never asserts UIX-7/UIX-8 runtime closure (UIX8C-R170). A fresh physical-device
  revalidation of R11 and the payment/sync UX remains mandatory after final code
  freeze (UIX8C-R169). Premium receipt/history UX continues in a later UIX-8C sprint.

## Scope guard for UIX-8C-06
- UIX-8C-06 builds the premium receipt / transaction-detail surface, the
  reconciled transaction history, and the typed printer failure-state experience
  (UIX8C-R171..R210): a receipt projection bound to one logical transaction by its
  stable `clientReference` and governed local/server identifiers, receipt/history/
  backend money-and-item parity, a truthful online/PENDING/SYNCING/SYNCED/FAILED/
  CONFLICT receipt state, a pure `TransactionHistoryReconciler` that dedupes to one
  row per logical transaction, durable reopen/reprint from Room, stale-result
  prevention via identity-carrying one-shot events, a single non-financial
  `PrinterCoordinator` with typed `PrinterFailure` states, a bounded print timeout,
  and least-privilege Bluetooth permissions (no `BLUETOOTH_SCAN`). It REUSES the
  UIX-8C-04/05 stable `clientReference`, `OfflineSaleRepository` durable
  persistence, `OfflineSyncStatus`, backend sale/payment idempotency, `RupiahMoney`,
  and `PaymentUiState` — it introduces NO second checkout, offline persistence,
  transport classifier, sync pipeline, or backend sale path. It does NOT change
  `SaleService`/backend financial behaviour beyond adding a regression fence (the
  backend already dedupes and owns the receipt), does NOT enable QRIS offline, does
  NOT make the printer a transaction authority, does NOT create a new transaction on
  reprint, does NOT run a physical campaign, does NOT flip the historical R11/R18
  evidence to PASS, and does NOT create a UIX-7 or UIX-8 GO tag. It MAY create the
  sprint-scoped tag `uix-8c-06-premium-receipt-history-printer-failure-states-go`
  under UIX8C-R002; that tag confirms source remediation + automated verification
  only and never asserts UIX-7/UIX-8 runtime closure (UIX8C-R210). A fresh
  physical-device revalidation of the receipt/history/printer/large-font/TalkBack
  scenarios remains mandatory after final code freeze (UIX8C-R209).

## Scope guard for UIX-8C-07
- UIX-8C-07 builds the premium startup/authentication experience, the
  device-activation flow, the operational Settings surface, and safe session /
  process recovery (UIX8C-R211..R250): a single deterministic startup/auth state
  machine (`BootState`/`StartupCoordinator`), a device-trust foundation (two
  distinct gates + server-authoritative device status), Keystore-backed secure
  storage for the token and installation id, one runtime-context source of truth,
  tenant-isolation fail-closed with an automated isolation test, unsynced-
  transaction protection on logout/switch/reset, cross-tenant cache hygiene,
  safe process/session restoration, truthful connection/sync/printer/session
  status, and accessibility/font-130% hardening. It REUSES the UIX-8C-04/05/06
  stable `clientReference`, `OfflineSaleRepository` durable persistence,
  `OfflineSyncStatus`, `PaymentUiState`, receipt/history projections, the backend
  Sanctum auth and the Sprint-34 device-activation/revocation domain — it
  introduces NO second checkout, offline persistence, sync pipeline, pricing,
  payment, or backend-sale engine. Backend change is limited to an additive,
  reversible device-status poll endpoint (`GET /api/v1/android/device/status`),
  additive activation columns (`app_version`, `installation_id`,
  `revocation_reason`), a single-use activation-code provisioning CLI (wiring the
  existing `DeviceActivationService::prepare()`), and activate rate-limit + audit;
  it does NOT change `SaleService`/financial behaviour, does NOT alter Room
  offline-transaction/`OFFLINE_PENDING` semantics, does NOT enable QRIS offline,
  does NOT run a physical campaign, does NOT flip the historical R11/R18 evidence
  to PASS, and does NOT create a UIX-7 or UIX-8 GO tag. It MAY create the
  sprint-scoped tag
  `uix-8c-07-premium-authentication-device-activation-settings-session-recovery-go`
  under UIX8C-R002; that tag confirms source remediation + automated verification
  (+ labelled emulator runtime evidence) only and never asserts UIX-7/UIX-8
  runtime closure (UIX8C-R249/R250). A fresh physical-device revalidation of the
  auth/activation/session/settings/large-font/TalkBack scenarios remains mandatory
  after final code freeze.

## ADR requirement
A material change to the delivery-train architecture, navigation graph, screen
state architecture, component architecture, adaptive layout, receipt/payment
state machine, or accessibility strategy requires an ADR under `docs/adr/`.
UIX-8C-01 is recorded by
`docs/adr/0004-uix-8c-full-premium-rebuild.md`; the UIX-8C-02 design-system
hardening, responsive cashier shell, and sprint-tag governance refinement are
recorded by `docs/adr/0005-uix-8c-02-premium-design-system-hardening.md`. The
UIX-8C-04 offline CASH durability & idempotent-recovery remediation is recorded
by `docs/adr/0006-uix-8c-04-offline-cash-durability.md`. The UIX-8C-05 premium
cash-payment sheet, payment/sync presentation state machine, and manual-retry /
reconnect recovery strategy are recorded by
`docs/adr/0007-uix-8c-05-payment-sync-state-machine.md`. The UIX-8C-06 receipt
projection / current-transaction binding, the local↔server history reconciliation
model, and the typed printer failure-state architecture are recorded by
`docs/adr/0008-uix-8c-06-receipt-history-printer-states.md`. The UIX-8C-07
deterministic startup/auth state machine, the runtime-context source of truth &
device-trust model, the Keystore-backed secure-storage decision, and the
cross-tenant cleanup / session-recovery strategy are recorded by
`docs/adr/0009-uix-8c-07-auth-device-settings-session-recovery.md`.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists
  and that `UIX8C-R001..R250` are persisted.
- `scripts/uix8c_auth_device_session_gate.sh` (fail-closed) enforces the
  UIX-8C-07 premium authentication / device-activation / settings / session-
  recovery baseline (UIX8C-R211..R250): rule persistence, the required docs and
  ADR 0009, the pure state machine (`BootState`/`StartupCoordinator`) and its
  transition tests, the Keystore-backed `SecureTokenStore` (no plaintext token,
  no jetpack-security dependency), the runtime-context source of truth, the
  server-authoritative device-status poll + revoked fail-closed (no bypass), the
  unsynced-logout guard counting all non-acked transactions, the classified
  `LocalDataCleaner` + automated tenant-isolation test, the process-restoration
  idempotency reuse of the UIX-8C-06 `clientReference`, the truthful status
  enums (status-not-colour-alone), the Settings no-secret-render invariant, the
  130%-font/accessibility tests, the backend device-status/provisioning tests,
  the immutable failed physical run, UIX-7/UIX-8 deferred status, and no premature
  UIX-7/UIX-8 GO tag. Its self-tests are
  `scripts/tests/uix8c_auth_device_session_gate_test.sh`.
- `scripts/uix8c_receipt_history_printer_gate.sh` (fail-closed) enforces the
  UIX-8C-06 premium receipt / transaction-history / printer failure-state baseline
  (UIX8C-R171..R210): rule persistence, the required docs, the pure presentation
  components (`ReceiptProjection`/`ReceiptProjector`, `TransactionHistoryReconciler`,
  `PrinterCoordinator`, typed `PrinterFailure`/`PrintResult`), receipt binding to a
  stable transaction identity, the whole-Rupiah parity tests, the local/server
  history merge and one-row-per-transaction tests, the PENDING/SYNCING/SYNCED/
  FAILED/CONFLICT distinctions, the printer non-financial-authority invariant
  (no sale/payment/sync/inventory reference in the printer package), reprint not
  calling checkout/payment, the bounded print retry, no `BLUETOOTH_SCAN`, the
  130%-font and accessibility tests, the immutable failed physical run, UIX-7/UIX-8
  deferred status, and no premature closure tag. Its self-tests are
  `scripts/tests/uix8c_receipt_history_printer_gate_test.sh`.
- `scripts/uix8c_payment_offline_sync_ux_gate.sh` (fail-closed) enforces the
  UIX-8C-05 premium payment / offline-sync recovery UX baseline
  (UIX8C-R131..R170): rule persistence, the required docs, the pure presentation
  components (`TenderValidator`, `QuickTenderCalculator`, `PaymentUiState(+Mapper)`,
  `SyncRecoveryPresenter`), the canonical whole-Rupiah formatter/parser reuse, no
  Float/Double on the money path, reuse of the UIX-8C-04 `TransportFailureClassifier`
  and offline persistence (no second classifier/persistence/`clientReference`/
  WorkManager pipeline), the ViewModel double-submit guard, insufficient-tender
  blocking, SYNCED-only-on-ack, QRIS-offline prohibition, the payment/state/
  restoration/manual-retry/font-scale/accessibility tests, the immutable failed
  physical run, UIX-7/UIX-8 deferred status, and no premature closure tag. Its
  self-tests are `scripts/tests/uix8c_payment_offline_sync_ux_gate_test.sh`.
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
