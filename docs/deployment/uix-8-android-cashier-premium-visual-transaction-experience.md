# UIX-8 — Android Cashier Premium Visual & Transaction Experience — Evidence

**Status: IMPLEMENTATION COMPLETE — GO DEFERRED** (honest terminal state per
UIX8-R048). GO is not fabricated: authoritative CI on the exact candidate SHA,
merge, VPS exact-match, operator-observed controlled-emulator runtime evidence,
and UIX-7 closure-debt resolution (or an auditable waiver) are all still required
and are operator/CI-performed.

Scope delivered: **Foundation + safety slice** (design-system governance, money
integrity, offline-sync durability, permanent rules, closure gate, evidence).
Not a full screen-by-screen visual rebuild — that and the deferrals below are
governed follow-ups (see ADR 0002).

## 1. Baseline & branch
- Baseline verified at start: `main = origin/main = 111799a`; UIX-7 GO tag
  **absent** (closure debt open); target `uix-8-*` tag **absent**.
- Feature branch: `feature/uix-8-android-cashier-premium-visual-transaction-experience`,
  cut from `origin/main` (`111799a`) — NOT from the unmerged UIX-7 tooling branch
  `13a8291`.
- Android module: `android/` — native Views/XML, package `com.aishtech.poslite`.

## 2. Toolchain (this environment CAN build/test Android now)
- Gradle 8.11, Kotlin 2.0.20, Android Studio JBR **JDK 21**
  (`~/.local/opt/android-studio/jbr`), SDK at `~/Android/Sdk` (android-34/35).
- Verify command: `JAVA_HOME=<JBR> ./gradlew :app:testDebugUnitTest`.

## 3. Audit findings (why this slice)
The on-device **design system already exists and is compliant**: Material 3
semantic tokens (`colors.xml`, `dimens.xml`, typography + `Widget.Aish.*` in
`styles.xml`, `themes.xml`), **zero hardcoded hex in layouts**. The real gaps
were financial integrity and sync durability:
- `RupiahMoney` (Long) existed but the live cart/checkout used `Double`.
- `CashierActivity` parsed tendered cash with `toDoubleOrNull() ?: 0.0` —
  mis-parses `"25.000"` as `25` and fabricates `0` on bad input.
- `getPendingOrFailed` re-selected FAILED rows forever → a poison row could
  starve the sync queue (attempt count WAS incremented; the cap was missing).

## 4. Changes delivered (all JVM-unit-verified)
### Money integrity (integer-exact; no Room schema change) — UIX8-R016/R017
- `CartItem` — `unitPriceRupiah`/`lineTotalRupiah` (Long) authoritative;
  `lineTotal: Double` becomes a projection.
- `CartRepository.subtotalRupiah(): Long` authoritative; `subtotal(): Double` is
  its projection (single money source of truth).
- `CashierViewModel.checkoutCash(Long)` / `checkoutCashOffline(Long)`;
  sufficiency & change via `RupiahMoney`; `OfflineSaved` totals are `Long`.
- `SalesRepository.checkoutCash(Long)` — DTO string contract unchanged
  (`"25000.00"`).
- `OfflineSaleRepository.createOfflineCashSale(Long)` — integer compute,
  projected to the unchanged `Double` Room columns at one boundary.
- `CashierActivity` — tendered cash parsed via `RupiahMoney.parse` (grouping-
  tolerant, rejects garbage, no fabricated 0).

### Bounded offline-sync retry (anti-starvation) — UIX8-R023
- `OfflineSaleRepository.MAX_SYNC_ATTEMPTS = 5`; `getPendingOrFailed(limit,
  maxAttempts)` excludes over-cap FAILED rows while keeping PENDING and orphaned
  SYNCING always eligible. Poison rows stay FAILED/visible, never dropped.

### Governance
- Permanent rules: `.claude/rules/56-…-foundation.md` (UIX8-R001..R048) +
  CLAUDE.md pointer. ADR `docs/adr/0002-…md`.
- Closure gate: `scripts/uix8_runtime_closure_gate.sh` (fail-closed,
  preflight/closure) + structured manifest `docs/deployment/uix-8-runtime-evidence.json`.

## 5. AUTOMATED TEST EVIDENCE
- Command: `./gradlew :app:testDebugUnitTest` → **BUILD SUCCESSFUL** (main +
  test compile clean; full unit suite green), local JBR JDK 21.
- New/updated tests: `CartMoneyIntegrityTest` (integer-exact cart totals),
  `OfflineSalesSyncLogicTest` (poison-row cap regression), and the migrated
  `Double→Long` cases in `SalesRepositoryTest`, `OfflineSaleRepositoryTest`,
  `OfflineSaleMappingTest`, `QrisOnlineOnlyGuardTest`, `OfflineTestFakes`.
- Closure gate preflight: `bash scripts/uix8_runtime_closure_gate.sh` → PASS.
- **Authoritative CI on the exact candidate SHA is still required (UIX8-R045).**

## 6. Governed deferrals (explicit, not silent — ADR 0002)
- **D1** dark-mode (`values-night`) + brand/logo/launcher assets.
- **D3** migrating the Room `offline_sales` money columns to `Long` (needs a
  schema-version bump + instrumented device migration test).
- **D5** cross-process durable online-reference reconciliation (process death
  after server commit, before response). Offline path already durable/idempotent.
- **D6** direct `CashierViewModel` JUnit test (would add
  `androidx.arch.core:core-testing`); double-submit/reference verified via
  operator emulator scenarios instead.

## 7. CONTROLLED EMULATOR EVIDENCE — PENDING (operator)
21 hardware-independent runtime scenarios (install/upgrade, activation, product
load, search, category, cart ops, background/process restoration, online
checkout, double-submit, stable reference, offline checkout, force-stop
restoration, reconnect sync, idempotent retry, receipt/history parity,
accessibility, font scaling, error states, crash/ANR/log). All **PENDING** in
`uix-8-runtime-evidence.json`. Must be operator-captured on the controlled AVD,
commit-/APK-bound, and redacted (UIX8-R041, UIX7-R062/R071..R080). Emulator
evidence stays labelled emulator; never fabricated from screenshot presence.

## 8. UIX-7 closure debt (unchanged, not fabricated)
UIX-7 remains IMPLEMENTATION COMPLETE — CLOSURE DEBT ACCEPTED; no UIX-7 GO tag.
UIX-8 did not alter UIX-7 evidence, did not create a UIX-7 GO tag, and did not
weaken UIX-7 idempotency/offline safeguards (it strengthened the bounded-retry
and money-integrity coverage of that area). Per UIX8-R043/R044, UIX-8 GO requires
UIX-7 closure OR a formal auditable time-bounded waiver.

## 9. Release facts — PENDING (operator/CI)
- Authoritative CI on candidate SHA: PENDING.
- Merge to `main`: PENDING. VPS sync + `www-data` ownership + HTTPS/live/ready:
  PENDING. DMS non-regression: PENDING. local=origin=VPS exact-match: PENDING.
- Annotated GO tag `uix-8-android-cashier-premium-visual-transaction-experience-go`:
  **not created** (blocked on all of the above + §7 + §8).

## 10. Decision
**IMPLEMENTATION COMPLETE — GO DEFERRED.** Absence of proof = NO-GO.
