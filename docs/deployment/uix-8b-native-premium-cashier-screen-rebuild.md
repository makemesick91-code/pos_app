# UIX-8B — Native Premium Cashier Screen Rebuild — Evidence Record

Status: **IMPLEMENTATION COMPLETE — GO DEFERRED** (all-variant build green, JVM
unit tests green, closure-gate preflight PASS; emulator/operator runtime evidence
and UIX-7 closure/waiver are operator-performed and outstanding — see §11).

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
All 7 cashier surfaces touched; 2 new surfaces added. All variants
(debug/release/pilot) build.
- **Cashier home + product experience** — money-integrity fix (removed the float
  `toDoubleOrNull() ?: 0.0` fabricated-zero on the online success summary → canonical
  `RupiahMoney.formatOrUnavailable(parse(...))`); authoritative integer cart total;
  truthful product-list states (Loading / EmptyCatalog / NoMatch / Error) that
  never clear the cart; tokenized + accessible; History nav entry.
- **Native cash payment** — new `PaymentSheetFragment` bottom sheet: amount due,
  quick round-up tenders + "Uang Pas", manual entry via `RupiahMoney.parse`, live
  integer-exact change, sufficiency validation, online/offline confirm. Delegates
  to the guarded `CashierViewModel` (no duplicated checkout/idempotency).
- **Success / receipt** — receipt is opened bound to the current sale id
  (`ReceiptActivity.EXTRA_SALE_ID`), never a "latest receipt" lookup; tokenized +
  accessible (print button labelled).
- **Transaction history** — new `TransactionHistoryActivity/ViewModel` +
  `OfflineSaleDao.getRecent`: one row per local sale, newest-first, tenant/device
  scoped, explicit accessible sync-state badges (text + colour, never colour
  alone), read-only.
- **QRIS payment** — tokenized; receipt affordance made a focusable, labelled
  control for TalkBack.
- **Login** — one-shot navigation via `Event` (rotation no longer re-launches the
  cashier/subscription screen); tokenized + labelled inputs.
- **Reports / Subscription** — tokenized + accessible.
- **Complete UI states** — loading/empty/no-match/error added to cashier and
  history; existing loading/blocked/error states on login/reports/subscription
  retained and tokenized.

## 6. Transaction safety (preserved, not weakened — extends rules 55/56)
- Integer-exact `RupiahMoney` (`Long`) on the authoritative path; cash tender via
  `RupiahMoney.parse` (never a fabricated 0); the cart total shown is the integer
  `subtotalRupiah`, never a recomputed float.
- Stable `clientReference` minted once per cart and reused on retry; ViewModel
  double-submit guard; durable-save-before-cart-clear; bounded sync retry
  (`MAX_SYNC_ATTEMPTS`); orphan-SYNCING recovery — all UIX-7/8A protections intact.
  The payment sheet holds no transaction authority; it only collects tender.

## 7. Accessibility
- Content descriptions on ambiguous/icon controls; labelled inputs; `>=48dp`
  touch targets via `@dimen/touch_target_min`; never-colour-alone status (text
  label always present). Zero raw `dp/sp` and zero hardcoded hex in layouts.
- Automated semantics coverage is partial; **TalkBack, focus order, and font-scale
  behaviour are operator-observed** (deferred — cannot be verified in this
  environment).

## 8. Tests
- Android unit (JVM): **120 `@Test` methods across 32 classes — all green**
  (`./gradlew :app:testDebugUnitTest`, JDK 21). New UIX-8B tests:
  `CashierProductsStateTest` (truthful empty state + money-summary contract),
  `PaymentTenderTest` (quick-tender ladder), `SyncStatusDisplayTest` (badge
  mapping, unknown≠synced), `EventTest` (one-shot contract).
- Instrumented/UI (Espresso), TalkBack, and on-device flows: **operator-deferred**.
- Backend targeted: no backend source changed in this sprint (Android-only
  remediation); backend suite unaffected.

## 8a. Build evidence (this environment)
- `./gradlew :app:assembleDebug :app:assembleRelease :app:assemblePilot` →
  `BUILD SUCCESSFUL` (all three variants, including the TLS-only pilot/release
  network config). APK SHA-256 binding to the final candidate is captured at the
  operator runtime step (§11), not fabricated here.

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

## 11. Operator runbook (deferred GO steps — operator-performed, never fabricated)

These steps cannot be done in the CI/build environment; they require a real
device/emulator, operator observation, and product-owner authority.

1. **Authoritative CI** — confirm the AISH POS Authoritative PR CI is green on the
   exact PR HEAD SHA (Android all-variants build + unit tests + foundation/closure
   gates + security). Merge only when green.
2. **VPS baseline sync** — deploy `main` so local = origin = VPS
   (`/var/www/aish-pos`); verify HTTPS, `/health/live`, `/health/ready`, `www-data`
   ownership of `storage/framework` + `bootstrap/cache`, services, no root-owned
   runtime files. (UIX-8B changes are Android-only; no backend migration.)
3. **DMS non-regression** — verify DaengtisiaMS HEAD unchanged, worktree clean,
   services active; NO-GO if affected (rule 80 / UIX8B-R095).
4. **Build the candidate APK** — `./gradlew :app:assemblePilot` on the exact
   candidate SHA; record `versionName`, variant, and `sha256sum` of the APK into
   `docs/deployment/uix-8-runtime-evidence.json` (`candidate_commit`, `apk_sha256`).
5. **Controlled emulator/device runtime** — run the scenarios in
   `docs/deployment/uix-8-runtime-evidence.json` (install, activation/login,
   product load/search/category, cart ops + restoration, online checkout via the
   tender sheet, double-submit, stable clientReference, offline checkout,
   process-kill restoration, reconnect idempotent sync, receipt parity, history
   parity, accessibility/TalkBack, font scaling, error states, crash/ANR/log
   scan). Each row needs a **substantive human PASS** + screenshot + shared run
   id/clientReference (UIX8B-R085..R090). Emulator evidence stays labelled
   emulator.
6. **UIX-7 debt** — resolve via Path A (genuine R11–R17 closure with a UIX-7
   annotated GO tag) OR Path B (a formal, auditable, time-bounded product-owner
   waiver that does NOT declare UIX-7 PASS). UIX-8 GO is blocked without one
   (UIX8B-R091/R092).
7. **Exact-match + closure gate** — with local = origin = VPS on the final commit:
   ```bash
   UIX8_CLOSURE_GATE_MODE=closure UIX8_CI_GREEN=true UIX8_PR_MERGED=true \
   UIX8_EXACT_MATCH=true UIX8_DMS_OK=true bash scripts/uix8_runtime_closure_gate.sh
   ```
   The manifest `decision` must be `GO` with zero non-PASS rows and UIX-7 debt
   closed/waived, or the gate fails closed.
8. **Annotated GO tag** — only after the gate PASSes:
   `uix-8-android-cashier-premium-visual-transaction-experience-go` (annotated),
   then remote-verify (`git cat-file -t`, peeled commit, `git ls-remote --tags`).
   Prior tags remain immutable.

Until steps 4–8 are genuinely satisfied, the honest terminal state is
**`IMPLEMENTATION COMPLETE — GO DEFERRED`** (absence of proof = NO-GO,
UIX8B-R098/R099).
