# UIX-8B — Native Premium Cashier Screen Rebuild — Evidence Record

Status: **IMPLEMENTATION IN PROGRESS** (honest terminal target for this
environment: `IMPLEMENTATION COMPLETE — GO DEFERRED`).

This document is the evidence record for the UIX-8B sprint. It is a living record
updated as work lands. **No GO tag** is created without genuine, operator-observed
runtime evidence and UIX-7 closure/waiver; nothing here is a placeholder PASS.

## 1. Baseline
- Repository: `makemesick91-code/pos_app`, branch `main`.
- Pre-sprint HEAD (local = origin): `d86dab6` (UIX-8A merge, PR #64).
- VPS at sprint start: `111799a` (behind main — sync verified/handled separately).
- UIX-8A status: `IMPLEMENTATION COMPLETE — GO DEFERRED` (design system, integer
  money, bounded retry, stable clientReference, double-submit guard delivered).
- Feature branch: `feature/uix-8b-native-premium-cashier-screen-rebuild`.

## 2. UIX-7 closure debt (explicit, unresolved)
- No `uix-7-*-go` tag exists. UIX-7 runtime closure debt is **OPEN**: R11 offline
  durable-save PASS chain and R12–R17 binding are not operator-verified here.
- UIX-8 GO is gated on UIX-7 closure (Path A) OR a genuine, auditable,
  time-bounded product-owner waiver (Path B). Neither is fabricated. Path A/B
  require operator action (emulator/device observation, product-owner sign-off)
  that cannot be produced in this build environment.

## 3. Environment capability (honest)
- This environment **can** build the Android module (JDK 21 + Android SDK,
  Gradle 8.11) and run **JVM unit tests** (`:app:testDebugUnitTest`).
- This environment **cannot** run instrumented/emulator UI tests, TalkBack/focus
  verification, on-device runtime, or capture operator-observed runtime evidence.
  Those rows stay PENDING/operator-gated — never fabricated (UIX8B-R086..R089).

## 4. Design system & rules
- Reuses the UIX-8A design system (`res/values/colors|dimens|styles|themes.xml`);
  zero hardcoded hex in layouts remains a standing invariant.
- Foundation persisted as permanent rules:
  `.claude/rules/57-android-cashier-premium-screen-rebuild.md`
  (UIX8B-R001..R100); CLAUDE.md pointer added; ADR
  `docs/adr/0003-uix8b-native-premium-cashier-screen-rebuild.md`.

## 5. Delivered screens
_(updated as each screen lands — see PR + commits)_
- Cashier home + product experience: _in progress_
- Cart: _in progress_
- Native cash payment: _in progress_
- Success / receipt (current-transaction binding): _in progress_
- Transaction history: _in progress_
- Complete UI states (loading/empty/offline/error/session/device): _in progress_

## 6. Transaction safety
- Integer-exact `RupiahMoney` (`Long`) on the authoritative path; cash tender via
  `RupiahMoney.parse`; stable `clientReference` reused on retry; ViewModel
  double-submit guard; durable-save-before-cart-clear; bounded sync retry.
  _(specifics per PR)._

## 7. Accessibility
- Labels, focus order, 48dp touch targets, font-scale resilience, never-colour-
  alone status. Automated semantics tests where feasible; TalkBack/focus is
  **operator-observed** (deferred).

## 8. Tests
_(updated with counts as tests land)_
- Android unit (JVM): _pending update_
- Backend targeted: _pending update_

## 9. Evidence sources (separated, per UIX7-R071)
- AUTOMATED TEST EVIDENCE — JVM unit tests (this environment).
- CONTROLLED EMULATOR EVIDENCE — **operator-performed** (deferred).
- OPERATOR EVIDENCE — explicit human PASS (deferred).
- DATABASE EVIDENCE — scoped VPS DB proof (deferred to deploy step).
- CI EVIDENCE — authoritative PR CI (added at PR).
- VPS EVIDENCE — deploy + exact-match + DMS non-regression (deferred).

## 10. Release decision
`GO DEFERRED` — runtime/operator evidence and UIX-7 closure/waiver are not met in
this environment. Absence of proof = NO-GO (UIX8B-R098/R099). Operator runbook
for the deferred steps is at the end of the final session report / this doc §11.

## 11. Operator runbook (deferred GO steps)
_(filled at end of implementation — emulator run, evidence capture, VPS deploy,
DMS check, closure-gate closure mode, annotated tag)._
