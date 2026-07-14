# ADR 0003 — UIX-8B Native Premium Cashier Screen Rebuild

- Status: Accepted
- Date: 2026-07-14
- Supersedes: none (extends ADR 0001 emulator-evidence governance and ADR 0002
  UIX-8A premium visual & transaction foundation)
- Related rules: `.claude/rules/57-android-cashier-premium-screen-rebuild.md`
  (UIX8B-R001..R100), extends 55 (UIX7) and 56 (UIX8-A).

## Context

UIX-8A delivered the on-device design system (Material 3 semantic tokens,
`Widget.Aish.*`/`TextAppearance.Aish.*`), integer-exact `RupiahMoney` (`Long`),
bounded offline-sync retry, the stable `clientReference` idempotency key, the
ViewModel double-submit guard, and durable-save-before-cart-clear. It did **not**
finish the premium rebuild of the actual cashier screens or the full
UI-state/accessibility matrix.

UIX-8B rebuilds the cashier surfaces (home, product experience, cart, native cash
payment, success/receipt, transaction history) and completes truthful UI states
and accessibility. Several material decisions were required; they are recorded
here because rule 57 requires an ADR for changes to navigation, screen
architecture, component architecture, adaptive layout, receipt state binding, the
payment state machine, or accessibility strategy.

## Decisions

1. **Framework stays native Views/XML.** No Jetpack Compose migration, no
   WebView cashier. A full Compose rewrite would be a large, risky architecture
   change with no transaction-safety benefit and is explicitly out of scope
   (UIX8B-R001/R002). Screens remain Activities with `viewBinding`, RecyclerView
   adapters, and `ViewModel`/`LiveData`.

2. **One authoritative state holder per screen.** Each screen exposes a single
   immutable UI-state object from its ViewModel via `LiveData`, plus a separate
   one-time-event channel for navigation/toasts that does **not** replay on
   configuration change or process recreation (UIX8B-R003/R008). Persistent
   (cart/offline queue), screen, one-time-event, and sync state stay distinct —
   no conflicting boolean flags.

3. **Payment state machine + native cash flow.** Checkout resolves a payment
   method first. Cash is a native flow (amount due → tender via
   `RupiahMoney.parse` → integer-exact change → submit-locked confirm). The
   payment states are `Idle → Submitting → (Success | Offline-Pending |
   Timeout/Unknown → Reconcile → Success/Failed)`. A retry reuses the existing
   `clientReference`; an unknown/timeout result reconciles and never re-rings a
   fresh transaction (UIX8B-R050..R055). QRIS stays hidden until its complete
   backend lifecycle is reachable and is online-only (UIX8B-R046).

4. **Receipt binds to the current transaction only.** The success/receipt screen
   is driven by the just-completed transaction's local id + `clientReference`,
   never a "latest receipt" lookup, eliminating stale-result display
   (UIX8B-R056/R057). Receipt and history read the same persisted transaction and
   use the same money formatter (UIX8B-R058/R063).

5. **Transaction history is per-transaction and scoped.** History lists each
   local/synced sale exactly once, shows explicit PENDING/SYNCING/SYNCED/FAILED
   badges (text + icon, never colour alone), stays tenant/outlet scoped, and any
   retry is idempotent on `clientReference` (UIX8B-R059..R064).

6. **Accessibility is a first-class acceptance criterion.** Every interactive
   control has an accessible name; icon-only controls carry `contentDescription`;
   touch targets meet `@dimen/touch_target_min` (48dp); critical state always has
   a text label; primary actions (checkout total, pay, new transaction) stay
   visible and operable under large font scale (UIX8B-R065..R076). TalkBack/focus
   verification is operator-observed (cannot be fully automated here).

7. **Adaptive layout via relative units + scroll containers.** Layouts use
   `match_parent`/weights and place wide/tall content in scroll containers so the
   product area stays dominant on small screens and primary actions are never
   clipped (UIX8B-R070/R084). No separate tablet resource set is introduced in
   this sprint.

## Consequences

- No new heavy dependency; APK growth is limited to drawables/strings and small
  view code (UIX8B-R082).
- Business truth stays in backend `App\Services\*` and canonical repositories/
  managers; screens/ViewModels present and orchestrate only.
- Instrumented UI, TalkBack, and on-device/emulator runtime verification remain
  **operator-performed**; this environment builds and runs JVM unit tests only.
- UIX-7 closure debt is unaffected: UIX-8B does not create a UIX-7 GO tag or flip
  UIX-7 evidence to PASS. UIX-8 GO stays gated on UIX-7 closure or a valid
  waiver; otherwise the honest terminal state is
  `IMPLEMENTATION COMPLETE — GO DEFERRED`.
