# UIX-8C — Full Premium Android Cashier Governance & Foundation

This is the foundation narrative for the UIX-8C delivery train. The enforceable
rule set lives in `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
(UIX8C-R001..R095) and is persisted in `docs/PROJECT_RULES.md`. This document
never overrides the modular rule; on any apparent conflict the modular rule is
authoritative.

## Purpose

UIX-8C is the **final premium Android delivery train** for genuine UIX-7/UIX-8
runtime closure of the native cashier (`com.aishtech.poslite`). It completes the
premium visual rebuild, truthful per-screen state, accessibility hardening, and
the physical-device closure campaign on top of UIX-8A (rule 56) and UIX-8B
(rule 57). It extends — never weakens — rules 55, 56, 57, 58, 59, 70/72, 80, 90.

## Current status (unchanged by UIX-8C-03)

- UIX-7: **NO-GO — GO DEFERRED**
- UIX-8: **IMPLEMENTATION COMPLETE — GO DEFERRED**
- R11 (offline CASH durability): **UNRESOLVED — OUT OF SCOPE this sprint**

Immutable failed physical run (preserved verbatim, UIX8C-R003):
`docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json`

- `run-97fbb64-2af94aa` — runtime anchor `97fbb64`, repo HEAD `2af94aa`,
  pilot APK sha256 `1a83931…`, decision **NO_GO**.
- R01 **PENDING** (identity not visible), R11 **FAIL** (offline CASH not
  durable), R18 **FAIL** (layout collapse at 130% font). These are never edited
  into PASS (UIX8C-R003/R030/R058).

## Delivery train

| Sprint | Scope | Outcome |
| --- | --- | --- |
| UIX-8C-01 | Governance, architecture, screen inventory, foundation gate, ADR 0004 | Rule set UIX8C-R001..R030; no runtime/visual/physical/GO. |
| UIX-8C-02 | Premium design system, reusable component library, responsive cashier shell (structural R18 fix), ADR 0005 | Rule set UIX8C-R031..R060; design-system gate; no runtime/financial change. |
| **UIX-8C-03** | **Premium cashier home + context header, product catalog, search, category filter, cart** | **Rule set UIX8C-R061..R095; catalog/cart gate; no backend/Room/financial change, no physical campaign.** |
| UIX-8C-04..09 | Authentication/device, payment & governed offline persistence, sync/idempotency, receipt/history, settings/printer & accessibility + physical closure | Continue the implementation train toward UIX-7/UIX-8 closure. |

## The foundation rules (summary)

Rules UIX8C-R001..R060 are summarized in the sprint 01/02 sections below. The
authoritative text for all rules is
`.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`.

Key non-negotiables carried across the whole train:

- UIX8C-R002 — no single **umbrella/final** UIX-8C GO tag; each implementation
  sprint MAY carry an immutable annotated **sprint-scoped** `uix-8c-NN-<slug>-go`
  tag that never asserts UIX-7/UIX-8 runtime closure (see UIX8C-R060/R095).
- UIX8C-R003 — historical failed physical evidence is immutable.
- UIX8C-R009 — whole-Rupiah integer money is mandatory.
- UIX8C-R012/R013/R014 — governed offline CASH fallback; canonical HTTP
  rejection never becomes offline success; cart clears only after durable save.
- UIX8C-R016 — duplicate sale/payment/inventory mutation is automatic NO-GO.
- UIX8C-R030 — absence of evidence remains NO-GO.

### UIX-8C-03 cashier-home, catalog & cart rules (R061..R095)

Introduced by this sprint as the permanent cashier-home / catalog / cart
baseline for every subsequent Android sprint. They extend — never weaken —
UIX8C-R001..R060 and rules 55/56/57.

- **UIX8C-R061** — Cashier home is the canonical combined catalog + cart +
  checkout surface (native Views/XML, single `CashierViewModel`).
- **UIX8C-R062** — Cashier context (business/outlet/cashier/device/network) comes
  from canonical authenticated state (`GET /api/v1/auth/me`); the UI never
  recomputes it.
- **UIX8C-R063** — Raw client-supplied tenant/outlet identity is never trusted;
  context is server-resolved only.
- **UIX8C-R064** — The cashier surface never exposes Platform Admin (`/admin/*`)
  or Tenant Owner (`/owner/*`) controls.
- **UIX8C-R065** — The product catalog is tenant/outlet scoped and bounded
  (`isActive=1` + `LIMIT` on every query).
- **UIX8C-R066** — The catalog defines distinct states: loading, loaded, empty,
  no-result, unavailable, offline-cached, error.
- **UIX8C-R067** — Loading is never presented as empty.
- **UIX8C-R068** — No-result (search/filter miss) is never presented as an empty
  catalog.
- **UIX8C-R069** — An error state preserves the current cart.
- **UIX8C-R070** — Offline-cached catalog data is labelled truthfully, never
  presented as fresh/online.
- **UIX8C-R071** — Stock status is conveyed with a text label, never colour alone.
- **UIX8C-R072** — Unavailable / out-of-stock products are not addable to the cart.
- **UIX8C-R073** — Prices use the canonical whole-rupiah integer formatter
  (`RupiahMoney`).
- **UIX8C-R074** — Search and category filtering never mutate cart state.
- **UIX8C-R075** — Clearing a filter/search restores the catalog under the
  current context.
- **UIX8C-R076** — Cart operations (add/increment/decrement/remove) are
  deterministic.
- **UIX8C-R077** — Cart quantities are never zero or negative.
- **UIX8C-R078** — Cart totals are whole-rupiah integer-exact.
- **UIX8C-R079** — No pricing/tax/stock business rules are computed in the UI
  layer.
- **UIX8C-R080** — Clear-cart requires explicit confirmation.
- **UIX8C-R081** — Cancelling the clear-cart confirmation preserves the cart.
- **UIX8C-R082** — Cart survives supported configuration changes.
- **UIX8C-R083** — Cart survives supported process recreation.
- **UIX8C-R084** — A catalog/product API error preserves a valid cart.
- **UIX8C-R085** — The checkout CTA is disabled when the cart is empty or invalid.
- **UIX8C-R086** — The checkout CTA remains visible or scroll-reachable at 130%
  font (never pushed off-screen).
- **UIX8C-R087** — Catalog and cart regions remain visible or scroll-reachable at
  130% font.
- **UIX8C-R088** — Long tenant/outlet/cashier/category/product names wrap or
  ellipsize, never clipping a primary action.
- **UIX8C-R089** — All interactive touch targets remain ≥48dp.
- **UIX8C-R090** — Interactive controls (including icon-only) expose accessible
  semantic labels.
- **UIX8C-R091** — Focus order follows context → search → categories → products →
  cart → totals → checkout.
- **UIX8C-R092** — Rendering stays lightweight (RecyclerView + DiffUtil; the main
  thread stays free of blocking I/O via `viewModelScope`).
- **UIX8C-R093** — Catalog/cart regression is a release blocker.
- **UIX8C-R094** — Development JVM/structural evidence never replaces physical
  catalog/cart + large-font + TalkBack closure.
- **UIX8C-R095** — A UIX-8C-03 sprint-scoped GO tag never asserts UIX-7 or UIX-8
  runtime closure.

### UIX-8C-04 offline CASH durability & idempotent-recovery rules (R096..R130)

UIX-8C-04 adds the permanent offline-durability / transport-safety / idempotency
baseline (UIX8C-R096..R130, rule 61): CASH-only offline capability, one stable
`clientReference` across the online attempt / fallback / restart / reconnect /
worker replay, a typed transport classifier (only genuine transport failures are
offline-eligible; HTTP 4xx/409, TLS, and unknown errors never are), atomic
durable Room persistence, cart-clear-only-after-durability with cart preservation
on save failure, distinct PENDING/SYNCING/SYNCED/FAILED/CONFLICT states, bounded
WorkManager retry, orphan-SYNCING recovery, backend-replay idempotency (exactly
one sale/payment/item-set, no duplicate inventory), truthful offline-queued UI,
and the automatic NO-GO on any durability/duplication/loss defect. The historical
failed physical R11 stays immutable (UIX8C-R129) and a UIX-8C-04 implementation
GO never implies UIX-7/UIX-8 runtime GO (UIX8C-R130).

### UIX-8C-05 premium cash payment & sync-recovery UX rules (R131..R170)

UIX-8C-05 adds the permanent payment-presentation / sync-recovery-UX / manual-retry
baseline (UIX8C-R131..R170, rule 61): the payment sheet is presentation-only and
delegates to the canonical ViewModel/repository; it REUSES the UIX-8C-04 transport
classifier, durable persistence, stable `clientReference`, WorkManager, and backend
idempotency (no second checkout/offline/sync/backend-sale path). Amount due, tender,
quick tender, and change are whole-Rupiah integer, locale- and overflow-safe;
insufficient tender can never submit and change is never negative. One visible
interaction owns at most one logical checkout (ViewModel double-submit guard);
online success shows only on server ack and offline-queued only on a durable commit.
The presentation states (Idle/EditingTender/Ready/SubmittingOnline/PersistingOffline/
OnlineSuccess/OfflineQueued/Pending/Syncing/RetryScheduled/Failed/Conflict/Synced)
are distinct; queued/PENDING never claims sync and SYNCED shows only on a recorded
ack; invalid transitions fail closed. Process restoration derives truth from Room,
never a stale UI event; reconnect feedback is informative and creates no duplicate
work; a SAFE manual retry reuses the existing transaction/`clientReference`, respects
bounded retry + worker coordination, and never runs for a CONFLICT/TLS/security
failure. Accessibility (labels, focus order, ≥48dp, colour-independent status) and
100/115/130% font resilience are release gates. The historical failed physical R11
stays immutable (UIX8C-R169) and a UIX-8C-05 implementation GO never implies
UIX-7/UIX-8 runtime GO (UIX8C-R170).

### UIX-8C-06 premium receipt, transaction history & printer failure-state rules (R171..R210)

UIX-8C-06 adds the permanent receipt-projection / history-reconciliation /
printer-reliability baseline (UIX8C-R171..R210, rule 61). The receipt and history
surfaces are projections of canonical transaction state and never create or mutate
a transaction. A receipt is bound to one logical transaction by its stable
`clientReference` and governed local/server identifiers; a previous transaction's
result can never surface for the current checkout. Online success shows only on
server ack; an offline receipt shows only after a durable local commit and is
labelled PENDING (SYNCED only on a recorded canonical ack). Receipt items,
quantities, unit prices, line totals, subtotal, total, tender, and change match the
canonical transaction exactly, in whole-Rupiah integers — never floating point.
Transaction history is reconciled to exactly one row per logical transaction: local
pending and server-confirmed records for the same `clientReference` merge rather
than duplicate, a payload mismatch surfaces CONFLICT, and PENDING/SYNCING/
RETRY_SCHEDULED/SYNCED/FAILED/CONFLICT stay distinct. Process restoration derives
truth from Room, never a stale UI event; reopen/reprint reads persisted data.
Printing is non-financial: availability, permission, connection, completion, or
failure never determine transaction success, print failure never rolls back or
duplicates a sale/payment, and reprint creates no new transaction. The printer
exposes typed failure states (permission required/denied, unsupported, adapter
disabled, not configured, unavailable, connection failed, timeout, write failed,
interrupted, unknown-safe) through a single bounded, non-financial coordinator;
permissions stay least-privilege with no `BLUETOOTH_SCAN`. Accessibility (labels,
focus order, ≥48dp, colour-independent status) and 100/115/130% font resilience are
release gates. The historical failed physical R11/R18 stays immutable (UIX8C-R209)
and a UIX-8C-06 implementation GO never implies UIX-7/UIX-8 runtime GO (UIX8C-R210).

## Scope of UIX-8C-01

Governance + architecture + inventory + foundation only: rule set
UIX8C-R001..R030 (rule 61), the dependency graph + screen inventory
(`docs/architecture/uix-8c-android-screen-state-architecture.md`), the
screen/state/accessibility matrix
(`docs/testing/uix-8c-screen-state-accessibility-matrix.md`), the delivery plan
(`docs/deployment/uix-8c-delivery-plan.md`), the fail-closed
`scripts/uix8c_foundation_gate.sh`, and ADR 0004. No runtime code, no physical
campaign, no GO tag.

## Scope of UIX-8C-02

Design-system hardening: rule set UIX8C-R031..R060, centralized premium tokens
(`colors|dimens|styles|themes|shapes.xml`), a reusable accessible component
library (`Widget.Aish.*`, `TextAppearance.Aish.*`, `component_state_*`, cashier
context header), a hardened responsive cashier shell fixing the structural R18
large-font failure, regression tests, `scripts/uix8c_design_system_gate.sh`, and
ADR 0005. No runtime/financial change; MAY carry the sprint-scoped tag
`uix-8c-02-premium-design-system-hardening-go`.

## Scope of UIX-8C-03 (this sprint)

UIX-8C-03 rebuilds the cashier home, product catalog, search, category filter,
and cart to a premium, truthful, accessible standard on top of the UIX-8C-02
responsive shell. It delivers, **without** a backend/Room/financial behaviour
change:

1. The permanent rule set UIX8C-R061..R095 (rule 61) and its persistence.
2. A category filter across `ProductDao` → `CatalogRepository` → `CashierViewModel`
   (bounded `isActive=1` + `LIMIT` queries; four combined query branches;
   filtering never mutates the cart) with a tokenized horizontal
   `CategoryFilterAdapter` (DiffUtil, ≥48dp chips, accessibility state, never
   colour-only).
3. A canonical cashier context header fed by `GET /api/v1/auth/me` through
   `AuthRepository.me()` and the pure `CashierContextPresenter`; missing values
   render "Tidak tersedia"; `online` is true only when identity resolved from the
   server this session (online ≠ merely connected).
4. A search-clear affordance and an error-state retry affordance that re-runs the
   current filter and never clears the cart.
5. Pure filter/state helpers (`CategoryOption.build`, `emptyProductsState`) and
   new pure-JVM tests; the full suite is now **173 tests / 0 failures**.

It does **not** fix R11, change `SaleService`/backend/Room semantics, alter
runtime evidence, run a physical campaign, or create a UIX-7/UIX-8 GO tag. It MAY
carry the sprint-scoped tag `uix-8c-03-premium-cashier-home-catalog-cart-go`
(UIX8C-R002/R060/R095), which records only this sprint's implementation closure.

## Scope of UIX-8C-04

UIX-8C-04 fixes the P1 offline CASH durability defect behind the physical R11
failure, **without** rebuilding the premium payment/receipt/history screens and
**without** changing `SaleService`/backend financial behaviour (the backend
already dedupes; UIX-8C-04 only adds backend idempotency regression tests). It
delivers: the rule set UIX8C-R096..R130; a typed
`core/network/TransportFailureClassifier`; a governed online→offline CASH fallback
in `SalesRepository.submitCash` + `CashierViewModel.checkoutCash`; a stable
`clientReference` reused across the online attempt/fallback/restart/reconnect/
worker replay; an idempotent, atomic durable `OfflineSaleRepository`/Room save
(`findByClientReference` reconciliation); cart-clear-only-after-durability; the
truthful `cashier_offline_waiting_sync` state; bounded WorkManager retry +
orphan-SYNCING recovery (preserved); Android + backend idempotency regressions;
and the fail-closed `scripts/uix8c_offline_cash_durability_gate.sh`. It does
**not** enable QRIS offline, run a physical campaign, flip the historical R11
evidence to PASS, or create a UIX-7/UIX-8 GO tag. It MAY carry the sprint-scoped
tag `uix-8c-04-offline-cash-durability-idempotent-recovery-go`. A fresh physical
R11 revalidation remains mandatory after final code freeze. UIX-7 stays `NO-GO —
GO DEFERRED` and UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`.

## Scope of UIX-8C-05

UIX-8C-05 builds the premium CASH payment sheet and the truthful offline-queue /
sync-recovery experience ON TOP OF the UIX-8C-04 transaction foundation, **without**
rebuilding the full receipt/history screens and **without** adding backend source
(only a regression fence; the backend already dedupes). It delivers: the rule set
UIX8C-R131..R170; pure, overflow-safe `TenderValidator` + `QuickTenderCalculator`;
a truthful payment/sync presentation state machine (`PaymentUiState` +
`PaymentUiStateMapper`, a durable save projects to OfflineQueued/Pending — never
Synced; SYNCED only on a recorded server ack; invalid transitions fail closed); a
governed `SyncRecoveryPresenter` (manual retry only for a still-retryable FAILED
row, never a CONFLICT/poison/in-flight row); the ViewModel double-submit guard,
process-restoration truth from Room, an informative reconnect signal, and a SAFE
manual retry — all REUSING the UIX-8C-04 `TransportFailureClassifier`,
`OfflineSaleRepository`, stable `clientReference`, `OfflineSalesSyncScheduler`/
WorkManager, and backend idempotency (no second checkout/offline/classifier/sync/
backend-sale path). It does **not** enable QRIS offline, convert a canonical/TLS
rejection into offline success, run a physical campaign, flip the historical R11
evidence to PASS, or create a UIX-7/UIX-8 GO tag. It MAY carry the sprint-scoped
tag `uix-8c-05-premium-cash-payment-offline-sync-recovery-go`. A fresh physical R11
+ payment/sync UX revalidation remains mandatory after final code freeze. UIX-7
stays `NO-GO — GO DEFERRED` and UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`.

## Scope of UIX-8C-06

UIX-8C-06 builds the premium receipt / transaction-detail surface, the reconciled
transaction history, and the typed printer failure-state experience
(UIX8C-R171..R210): an immutable `ReceiptProjection` bound to one logical
transaction, a pure `ReceiptProjector` (local + server sources, one parity type), a
pure `TransactionHistoryReconciler` (one row per logical transaction, merge/dedup/
conflict), durable reopen/reprint from Room, stale-result prevention via
identity-carrying one-shot events, and a single non-financial `PrinterCoordinator`
with typed `PrinterFailure` states, a bounded print timeout, and least-privilege
Bluetooth permissions (no `BLUETOOTH_SCAN`). It REUSES the UIX-8C-04/05 stable
`clientReference`, `OfflineSaleRepository`, `OfflineSyncStatus`, `RupiahMoney`,
`PaymentUiState`, and backend idempotency — no second transaction path. It does NOT
change `SaleService`/backend financial behaviour beyond a regression fence, does NOT
enable QRIS offline, does NOT make the printer a transaction authority, does NOT run
a physical campaign, and does NOT flip the historical R11/R18 evidence to PASS. It
MAY create the sprint tag
`uix-8c-06-premium-receipt-history-printer-failure-states-go`; that tag never
asserts UIX-7/UIX-8 runtime closure. A fresh physical receipt/history/printer/
large-font/TalkBack revalidation stays mandatory after final code freeze. UIX-7
stays `NO-GO — GO DEFERRED` and UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`.

## Enforcement gates

- `scripts/verify_application_foundation_rules.sh` — checks rule 61 exists and
  that `UIX8C-R001..R210` are persisted.
- `scripts/uix8c_receipt_history_printer_gate.sh` (fail-closed) — the UIX-8C-06
  premium receipt / transaction-history / printer failure-state baseline
  (UIX8C-R171..R210): rule persistence, required docs, the pure presentation
  components (`ReceiptProjection`/`ReceiptProjector`, `TransactionHistoryReconciler`,
  `PrinterCoordinator`, typed `PrinterFailure`/`PrintResult`), receipt binding to a
  stable identity, whole-Rupiah parity tests, local/server merge + one-row-per-
  transaction tests, the state distinctions, printer non-financial authority
  (no sale/payment/sync/inventory reference in the printer package), reprint not
  calling checkout, bounded retry, no `BLUETOOTH_SCAN`, 130%-font + accessibility
  tests, immutable failed run, UIX-7/UIX-8 deferred, no premature GO. Self-tests:
  `scripts/tests/uix8c_receipt_history_printer_gate_test.sh`.
- `scripts/uix8c_payment_offline_sync_ux_gate.sh` (fail-closed) — the UIX-8C-05
  premium payment / offline-sync recovery UX baseline (UIX8C-R131..R170): the pure
  presentation components, whole-Rupiah reuse, no Float/Double money, reuse of the
  UIX-8C-04 classifier/persistence/`clientReference`/WorkManager (no second path),
  the double-submit guard, insufficient-tender blocking, SYNCED-only-on-ack,
  QRIS-offline prohibition, the payment/state/restoration/manual-retry/font-scale/
  accessibility tests, immutable failed physical run, and UIX-7/UIX-8 deferred
  status. Self-tests: `scripts/tests/uix8c_payment_offline_sync_ux_gate_test.sh`.
- `scripts/uix8c_offline_cash_durability_gate.sh` (fail-closed) — the UIX-8C-04
  offline CASH durability baseline (R096..R130): typed classifier, no catch-all
  offline fallback, QRIS-offline prohibition, atomic Room save,
  cart-clear-after-save, stable `clientReference`, bounded retry, orphan
  recovery, Android + backend idempotency tests, no float money, immutable
  failed-run, UIX-7/UIX-8 deferred, no premature GO.
- `scripts/uix8c_foundation_gate.sh` (fail-closed) — governance, inventory,
  immutable failed-run record, UIX-7/UIX-8 deferred status, no premature GO.
- `scripts/uix8c_design_system_gate.sh` (fail-closed) — the UIX-8C-02
  visual/responsive/accessibility baseline (tokens, components, no hardcode,
  48dp, font-scale test presence, status-not-colour-alone).
- `scripts/uix8c_cashier_catalog_cart_gate.sh` (fail-closed) — the UIX-8C-03
  cashier-home / catalog / cart baseline (context header wiring, category row,
  filter-never-mutates-cart, distinct catalog states, checkout CTA
  scroll-reachable at 130% font, ≥48dp targets, no hardcoded hex).

All are wired into the authoritative CI foundation lane
(`.github/workflows/_foundation-gates.yml`).

## How closure will happen

The implementation train (UIX-8C-03..09) remediates the screens and the failed
findings; each sprint is exact-SHA CI-gated (UIX8C-R027/R028) and MAY carry its
own immutable annotated **sprint-scoped** GO tag (UIX8C-R002). After code freeze
(UIX8C-R005/R024) a fresh APK (UIX8C-R004) drives the physical campaign; genuine
UIX-7/UIX-8 closure — including the physical catalog/cart, large-font
(100/115/130%), and TalkBack rows — is recorded against the existing UIX-7/UIX-8
GO discipline (rules 55/56/59/90). UIX-8C mints **no umbrella/final** tag of its
own (UIX8C-R002), and no sprint tag asserts UIX-7/UIX-8 runtime closure
(UIX8C-R060/R095). Absence of proof stays NO-GO (UIX8C-R030/R094).
