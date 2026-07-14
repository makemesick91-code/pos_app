# UIX-8C — Delivery Plan (UIX-8C-02 … UIX-8C-09)

Rule set: `.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`
(UIX8C-R001..R030). This plan is authored in UIX-8C-01; it schedules the
implementation train and does not execute it. No sprint below mints a UIX-8C GO
tag (UIX8C-R002); each is merged only behind an authoritative exact-SHA CI with
green gates (UIX8C-R027/R028).

## Train principles

- One concern per sprint; each leaves `main` green and deployable (rule 70).
- Runtime changes invalidate old APK evidence (UIX8C-R004); a fresh APK + fresh
  exact-SHA-bound evidence is required.
- Physical testing starts only after code freeze (UIX8C-R005/R024).
- Business truth stays in canonical services (UIX8C-R008); each sprint is
  presentation/state/persistence-discipline only.
- The immutable failed run `run-97fbb64-2af94aa` (R01/R11/R18) is never edited
  to PASS (UIX8C-R003).

## Sprints

### UIX-8C-02 — Premium design-system hardening & component library
- `Widget.Aish.*` / `TextAppearance.Aish.*` completion; zero raw hex/spacing in
  changed layouts (UIX8C-R017/R018); brand gradient limited; elevation via
  surface/border. Design-gate coverage.
- Exit: design gate green, no runtime behaviour change.

### UIX-8C-03 — Authentication / device / activation rebuild
- Splash, activation, login, expired-session, activation-failure,
  device-unavailable, logout/account-switch surfaces + states (UIX8C-R006).
- Cross-tenant identity clear on switch (UIX8C-R011); server-resolved context.
- Exit: auth/device unit tests green.

### UIX-8C-04 — Cashier home + product / search / category experience
- Home, context header (**R01 remediation**, UIX8C-R010), products, search
  (no cart mutation), categories, empty/loading/error/no-match/offline-cached
  states (UIX8C-R006/R007); loading never erases cart (UIX8C-R014).
- Exit: cashier product-state tests green; R01 planned-closed (physical later).

### UIX-8C-05 — Cart experience
- Cart, empty-cart, deterministic add/increment/decrement/remove,
  clear-cart confirmation, integer-exact totals (UIX8C-R009), survives
  process recreation; summary matches transaction request.
- Exit: cart money-integrity + state tests green.

### UIX-8C-06 — Payment + governed offline persistence (**R11 remediation**)
- Cash payment sheet, quick/manual tender (`RupiahMoney.parse`), insufficient
  cash, submitting (double-submit guard), online success, **offline queued with
  durable save before cart clear** (UIX8C-R012/R014), canonical rejection never
  becomes offline success (UIX8C-R013); stable `clientReference` (UIX8C-R015).
- Exit: offline durability + idempotency tests green; R11 planned-closed
  (physical confirmation deferred to closure campaign).

### UIX-8C-07 — Sync / WorkManager / idempotency states
- Pending/syncing/synced/retrying/failed/conflict/reconnect + orphan-SYNCING
  recovery states (UIX8C-R006); bounded retry; synced only on server ack;
  exactly one canonical transaction (UIX8C-R016).
- Exit: sync-logic + bounded-retry + orphan-recovery tests green.

### UIX-8C-08 — Receipt + transaction history / detail
- Current/offline/synced receipt bound to current txn; history (loading/empty/
  error/pending/failed), transaction detail; single money formatter; status not
  colour-only (UIX8C-R022).
- Exit: receipt/history parity tests green.

### UIX-8C-09 — Settings / device / printer + accessibility + physical closure
- Settings (cashier identity, tenant/outlet, device status, app version,
  network/sync status, printer status, logout); **accessibility hardening**:
  >=48dp targets, TalkBack/focus/labels, **130% font operability (R18
  remediation, UIX8C-R021)**, long-name layout safety (UIX8C-R023).
- Code freeze (UIX8C-R005/R024) -> fresh APK (UIX8C-R004) -> physical-device
  runtime campaign via the operator runners; closure recorded against existing
  UIX-7/UIX-8 GO discipline (rules 55/56/59/90). No UIX-8C GO tag (UIX8C-R002).

## Closure gate chain

Physical closure reuses `scripts/uix7_runtime_closure_gate.sh` and
`scripts/uix8_runtime_closure_gate.sh` (fail-closed) and the operator runners
(`scripts/uix7_operator_runner.sh`, `scripts/uix8_operator_runner.sh`). Absence
of proof stays NO-GO (UIX8C-R030). Prior GO tags remain immutable (UIX8C-R029).
