# ADR 0002 — UIX-8 Android cashier premium visual & transaction foundation

- Status: **Accepted**
- Date: 2026-07-14
- Deciders: Principal Android Engineer, Release Governance (UIX-8)
- Related: `.claude/rules/56-android-cashier-premium-visual-transaction-foundation.md`
  (UIX8-R001..R048), rule 55 (UIX7-R001..R080), ADR 0001, rule 90.

## Context

UIX-8 remediates the native Android Cashier's visual and transaction experience.
An audit of the current app (native Views/XML, package `com.aishtech.poslite`)
found that the on-device design-token system already exists and is mature
(Material 3, semantic `colors.xml`/`dimens.xml`/`styles.xml`, `Widget.Aish.*`
component styles, **zero hardcoded hex in layouts**). The real, high-value gaps
were in transaction financial integrity and offline-sync durability, not in a
missing design system. UIX-7 closure debt remains open and no UIX-7 GO tag
exists.

This environment can now genuinely compile and unit-test the app (Gradle 8.11 +
Kotlin 2.0.20 on the Android Studio JBR JDK 21), so Kotlin changes here are
verified by `./gradlew :app:testDebugUnitTest`, not assumed.

## Decisions

### D1 — Reuse and formalise the existing design system; defer dark mode & brand assets
The token system is already the single visual source of truth. UIX-8 formalises
it as governed rules (UIX8-R005..R011) rather than rebuilding it. A `values-night`
dark variant and brand/logo drawables/launcher assets are **deferred**: flipping
the app theme to `DayNight` without on-device visual verification is risky, and
brand assets are a design-asset task. Both are follow-ups, tracked here, not
half-shipped blind.

### D2 — Integer-exact money on the authoritative path, Double only at the edges
`RupiahMoney` (`Long`, whole rupiah) already existed but was not wired into the
live cart/checkout arithmetic, which used `Double`. The in-memory cart is **not**
Room-backed, so its arithmetic was migrated to `Long` (`CartItem.lineTotalRupiah`,
`CartRepository.subtotalRupiah()`, `checkoutCash(Long)`, `checkoutCashOffline(Long)`,
`SalesRepository.checkoutCash(Long)`, `OfflineSaleRepository.createOfflineCashSale(Long)`)
with sufficiency/change via `RupiahMoney`. The legacy `Double` values (`subtotal()`,
`lineTotal`, the Room `offline_sales` columns, and the sale DTO string) are kept
as **projections of the integer value at exactly one boundary each** — a single
money source of truth, no parallel computation, and **no Room schema migration**.
Tendered cash is now parsed via `RupiahMoney.parse` (tolerates grouping, discards
decimals, rejects garbage) instead of `toDoubleOrNull() ?: 0.0`, which mis-parsed
`"25.000"` as `25` and fabricated `0` on bad input. Verified by
`CartMoneyIntegrityTest` and the migrated repository tests.

### D3 — Deferred: migrate the Room `offline_sales` money columns to Long
Making the persisted columns `Long` requires a Room schema-version bump and a
tested migration on real device data. That is a governed follow-up (its own PR
with an instrumented migration test), not something to ship without the ability
to run a device/instrumented migration test here. Until then the storage columns
stay `Double`, written only via the D2 projection boundary.

### D4 — Bounded offline-sync retry (anti-starvation)
`markFailed`/`markConflict` already increment `syncAttemptCount`, but
`getPendingOrFailed` re-selected FAILED rows forever, so a permanently-failing
("poison") row — ordered oldest-first — could consume the LIMIT window and starve
newer sales from ever syncing. UIX-8 caps FAILED eligibility at
`OfflineSaleRepository.MAX_SYNC_ATTEMPTS`; past the cap the row **stays FAILED and
visible** (counted, surfaced), it just stops auto-retrying. PENDING and orphaned
SYNCING rows are never capped, preserving UIX-7 orphan recovery. Verified by
`OfflineSalesSyncLogicTest`.

### D5 — Deferred: cross-process durable online-reference reconciliation
The online `clientReference` is minted in the ViewModel, reused across in-session
retries, and reset on cart mutation — correct for the config-change/in-session
case. The remaining risk is process death **after** the server commits but
**before** the response returns: the in-memory cart (and its reference) are lost,
so a re-ring becomes a new reference and could duplicate the server sale. Closing
this needs a durable pending-online record + reconciliation-on-relaunch (like the
offline queue) — a genuine feature, not a blind patch. It is deferred to a
governed follow-up. The offline path is already durable and idempotent.

### D6 — ViewModel double-submit/reference test coverage
The ViewModel-level double-submit guard and reference reuse/reset are preserved
(UIX8-R018/R019). A direct JUnit test of `CashierViewModel` would require adding
`androidx.arch.core:core-testing` (for `InstantTaskExecutorRule`) plus a main-
dispatcher rule. To avoid a dependency change (which forces full CI under
CICD-CTRL-2) for a one-line guard, this behaviour is verified by operator-observed
controlled-emulator runtime scenarios (double-submit; stable reference) rather
than an added unit-test dependency. Adding that dependency + tests is an
acceptable future improvement.

## Consequences

- UIX-8 ships integer-exact money and bounded retry, verified by the JVM unit
  suite (green), touching no Room schema and no DTO contract.
- D1/D3/D5/D6 are explicit, tracked deferrals — not silent omissions.
- UIX-7 closure debt is untouched and un-fabricated; UIX-8 GO remains gated on
  UIX-7 closure or an auditable waiver (UIX8-R043/R044), else GO DEFERRED.
