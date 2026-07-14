# UIX-7 — Android Cashier Experience Remediation (Foundation)

Aish POS's sixth+ surface, the **Android Cashier app** (`com.aishtech.poslite`),
is the tenant/device-scoped point-of-sale client used at the counter. UIX-7
remediates the cashier experience — durability, financial integrity, truthful
state, security, accessibility, and performance — over the existing Android and
backend domain services. It is a remediation, not a feature expansion, and it
never becomes a second pricing, payment, QRIS, settlement, or sync engine.

The modular enforceable rule is `.claude/rules/55-android-cashier-experience.md`.
The rule-set IDs (UIX7-R001..UIX7-R070) are mirrored in
`docs/PROJECT_RULES.md`. This document is the narrative foundation.

## App shape (baseline)
- Native Views/XML UI (no Compose), Retrofit/OkHttp networking, Room + KSP local
  catalog/offline-sale queue, WorkManager background sync, ViewModel/LiveData.
- `applicationId com.aishtech.poslite`, `minSdk 26`, `targetSdk 35`,
  `compileSdk 35`, JVM target 17, `versionCode 1` / `versionName 0.1.0`.
- Canonical local services reused (never forked): `CartRepository`,
  `OfflineSaleRepository` (+ `OfflineSaleDao`), `CatalogSyncManager`,
  `OfflineSalesSyncWorker`/`Scheduler`, `SalesRepository`, `QrisRepository` +
  `QrisOnlineOnlyGuard`, `ReceiptRepository`, `SessionManager`/`TokenStore`,
  `DeviceIdentityStore`, `AndroidRuntimeState`.

## Cashier journeys covered
Launch → login → device activation/heartbeat → cashier home → local product
search → cart → checkout (online CASH / offline CASH draft) → payment (CASH /
QRIS online-only) → receipt → reports → offline queue + manual/background sync →
logout. Each is presented from canonical state; the app adds no business truth.

## Remediation shipped in UIX-7 (code)
- **Financial integrity (UIX7-R018/R019):** new canonical whole-rupiah money type
  `core/money/RupiahMoney` (`Long`, integer-exact parse/lineTotal/subtotal/change/
  format + `Tidak tersedia` for unknown), unit-tested; cashier money formatting
  routed through the single canonical formatter.
- **Offline durability (UIX7-R009/R011/R012):** an offline sale interrupted after
  `markSyncing` but before the server response was stranded in `SYNCING` and never
  retried (silent loss). The retry queue now recovers orphaned `SYNCING` rows;
  replay is safe because the submit is idempotent on the device `clientReference`.
  Regression-tested.
- **Duplicate-submit protection (UIX7-R015/R025):** ViewModel-level re-entry guard
  on both `checkoutCash` and `checkoutCashOffline`, closing the tap-before-observer
  race that the UI-only button disable left open.
- **Destructive-action safety (UIX7-R016):** the clear-cart button now shows the
  canonical UIX-1 confirmation before discarding an in-progress sale.
- **Transport & data security (UIX7-R006/R007/R026/R027):** `allowBackup=false`,
  a `network_security_config` that denies cleartext by default (dev hosts only),
  and a build-typed API base URL whose release/pilot default is
  `https://aishpos.online/`. The OkHttp logger already redacts `Authorization`
  and runs in debug only.

## Explicitly deferred (governed follow-up, documented — not silently skipped)
- Migrating the persisted Room money columns (`LocalProductEntity`,
  `LocalOfflineSaleEntity`) and DTO money from `Double` to whole-rupiah `Long`
  requires a Room schema migration and instrumented (device) tests to validate
  data integrity. Doing that without a device/emulator gate would risk the exact
  destructive-migration failure the rules forbid, so the migration is deferred;
  `RupiahMoney` is the canonical target for that work and guards new float money.
- Persisting cart across full process death (SavedStateHandle/Room draft) beyond
  the ViewModel's config-change survival (UIX7-R014) is a follow-up.

## Verification model (this environment)
This build environment has no Android SDK/emulator/device and JDK 25, so the
authoritative gate is **CI (JDK 21)**: `:app` unit tests (incl. `RupiahMoneyTest`
and the offline-sync recovery test), the UIX-7 Android design gate, the foundation
gate, and the backend regression suite. On-device authenticated runtime
verification (UIX7-R039) against `https://aishpos.online` is performed by an
operator on an approved device/emulator; GO is deferred until that evidence is
captured (UIX7-R044). No runtime evidence is fabricated.

## Rule-set IDs
- UIX7-R001 — Android Cashier is a distinct authenticated surface, never web-console auth.
- UIX7-R002 — Backend/Android domain services remain canonical.
- UIX7-R003 — No duplicated pricing/tax/discount/entitlement/payment/QRIS/settlement/sync logic in UI.
- UIX7-R004 — Tenant/outlet/user/device context resolved from authenticated canonical state.
- UIX7-R005 — Raw client-supplied tenant/outlet ids never trusted as authorization.
- UIX7-R006 — Local DB/cache/files/prefs/background work tenant/device/user scoped; backup disabled.
- UIX7-R007 — No cross-tenant local/cached residue after account/device switch.
- UIX7-R008 — Offline txn durably persisted before success is shown.
- UIX7-R009 — Network never required to preserve a valid offline txn; interrupted sync recoverable.
- UIX7-R010 — Idempotent sync retries (clientReference); no duplicate server txns.
- UIX7-R011 — PENDING/SYNCING/SYNCED/FAILED/CONFLICT stay distinct.
- UIX7-R012 — Unknown/stale sync state never shown as synced; SYNCED only on server ack.
- UIX7-R013 — Conflict resolution canonical; never silently overwrites authoritative data.
- UIX7-R014 — Cart survives config change and process recreation.
- UIX7-R015 — Checkout prevents accidental double submission (ViewModel guard).
- UIX7-R016 — Logout/reset/switch never silently discards unsynced txns; destructive cart confirmed.
- UIX7-R017 — Reactivation/account-switch governs local tenant-scoped data explicitly.
- UIX7-R018 — Canonical whole-rupiah integer money; no unsafe float in new/changed cashier code.
- UIX7-R019 — Totals/paid/change/receipt from canonical calc, formatted only via RupiahMoney.format.
- UIX7-R020 — QRIS created/pending/paid/confirmed/settlement-pending/settled/failed/expired distinct.
- UIX7-R021 — QRIS creation alone never displayed as paid/settled.
- UIX7-R022 — Offline never claims QRIS success without canonical confirmation; QRIS online-only.
- UIX7-R023 — Receipt and history show canonical, mutually consistent values.
- UIX7-R024 — Loading/unavailable/offline/pending/failed/retrying/conflict/success are truthful.
- UIX7-R025 — Transaction-creating actions have safe progress + duplicate-tap protection.
- UIX7-R026 — No credentials/tokens/payloads/PII in logs/analytics/crash/screenshots/test artifacts.
- UIX7-R027 — Secure-storage for tokens/device creds; cleartext denied by default.
- UIX7-R028 — Exported components/deep-links/intents/file-sharing least-privilege.
- UIX7-R029 — Existing Aish design tokens/components; no hardcoded off-system colors.
- UIX7-R030 — Status never color-only.
- UIX7-R031 — Touch targets/TalkBack/focus order/content descriptions/font scaling are release gates.
- UIX7-R032 — Phone and tablet layouts keep primary actions and totals accessible.
- UIX7-R033 — No main-thread disk/DB/network I/O.
- UIX7-R034 — Background sync/polling respects battery/network/retry/scheduling.
- UIX7-R035 — No aggressive unbounded polling.
- UIX7-R036 — Crash/ANR/duplicate-txn/lost-offline-txn/cross-tenant-leak are automatic NO-GO.
- UIX7-R037 — Performance budgets from measured baseline, never fabricated.
- UIX7-R038 — Release artifacts traceable to commit/package/version/variant/hash.
- UIX7-R039 — Pilot runtime verification uses an installable artifact vs HTTPS aishpos.online with synthetic data.
- UIX7-R040 — Synthetic accounts/devices/products/txns cleaned and cleanup verified.
- UIX7-R041 — Production Artisan ops preserve PHP-FPM ownership of storage/framework and bootstrap/cache.
- UIX7-R042 — Composer --no-dev verification not reliant on Faker/dev packages.
- UIX7-R043 — Shared-VPS sync must not change/regress DaengtisiaMS.
- UIX7-R044 — GO requires authoritative CI, device runtime verification, evidence closure, local/origin/VPS exact match, immutable previous tags.

## Build-variant endpoint & physical-device pilot connectivity

The original UIX-7 remediation build-typed the API base URL but left the default
`debug` variant (which produced the downloaded `app-debug.apk`) pointing at the
Android Emulator host alias `http://10.0.2.2:8000/`. `10.0.2.2` only resolves the
developer host from inside the emulator, so on a real phone the app could not
reach the backend and showed "Tidak dapat terhubung ke server" — even though
`https://aishpos.online/` login was independently verified HTTP 200. The fix adds
a dedicated, installable `pilot` build variant that targets the governed HTTPS
backend and isolates the emulator cleartext exceptions to the debug source set.

- UIX7-R045 — Emulator development and physical-device pilot API endpoints use explicit separate build variants (`debug` vs `pilot`).
- UIX7-R046 — Debug emulator builds may use the `10.0.2.2` host alias; pilot and release builds must use the governed HTTPS backend (`https://aishpos.online/`).
- UIX7-R047 — Pilot and release variants deny cleartext and never use trust-all TLS or disabled hostname validation; HTTP logging does not run for the debuggable pilot variant.
- UIX7-R048 — Localhost/emulator cleartext exceptions stay in the debug-only source set (`src/debug/res/xml`) and never enter pilot/release manifests.
- UIX7-R049 — A physical-device pilot APK is installable, approved-cert signed, source-traceable, and verified to contain the governed pilot HTTPS API URL.
- UIX7-R050 — Connection-error investigation distinguishes DNS, TLS, transport, authentication, authorization, and invalid-build-endpoint failures from observed evidence.
- UIX7-R051 — UIX-7 GO stays blocked until physical-device authenticated verification, offline/reconnect verification, synthetic cleanup, and evidence closure are complete; on-device evidence is operator-captured, never fabricated.

### Physical-device runtime closure & GO discipline (UIX7-R052..UIX7-R070)

- UIX7-R052 — Device activation binds the authenticated Cashier, tenant, outlet, device identifier, and activation state without granting cross-tenant or elevated access.
- UIX7-R053 — A transaction is presented as successful only after the required durable local save (offline) or canonical server acknowledgement (online).
- UIX7-R054 — Every transaction attempt uses a stable idempotency key (`clientReference`) preserved across retries, process restart, and reconnect.
- UIX7-R055 — Rapid tap, retry, reconnect, and worker replay produce exactly one canonical financial transaction.
- UIX7-R056 — Offline transactions survive force-stop, process death, application restart, device restart where supported, and temporary loss of connectivity.
- UIX7-R057 — A local transaction transitions to synced only after canonical server acknowledgement is durably recorded.
- UIX7-R058 — Cart, transaction, payment, change, receipt, history, and backend totals match exactly using integer monetary units (whole rupiah).
- UIX7-R059 — A stale previous receipt or transaction result is never displayed as the result of the current cart.
- UIX7-R060 — QRIS created or awaiting payment is never presented as paid, confirmed, settled, or successful.
- UIX7-R061 — QRIS status transitions are monotonic, auditable, idempotent, tenant-scoped, and correlated to exactly one transaction.
- UIX7-R062 — Runtime evidence is captured from an actual physical device, never replaced by emulator or unit-test evidence.
- UIX7-R063 — Runtime logs and screenshots redact credentials, tokens, customer PII, payment secrets, and QR payloads.
- UIX7-R064 — Accessibility verification includes TalkBack, focus order, semantic labels, touch targets, font scaling, error announcements, and the primary cashier workflows.
- UIX7-R065 — All synthetic Cashier, device, product, transaction, payment, QRIS, sync queue, and test artifacts are removed or deactivated before UIX-7 GO.
- UIX7-R066 — UIX-7 GO requires local, origin, VPS, final evidence commit, and annotated tag peeled commit to exact-match.
- UIX7-R067 — Any runtime-discovered source defect requires regression tests and one authoritative full CI on the final candidate.
- UIX7-R068 — Evidence-only closure uses lightweight CI only when the CICD-CTRL-2 classifier proves no executable, source, workflow, rules, dependency, schema, config, or test file changed.
- UIX7-R069 — A runtime defect involving financial correctness, transaction loss, duplication, authorization, tenant isolation, QRIS false-success, or credential leakage is an automatic NO-GO.
- UIX7-R070 — Physical-device runtime verification, cleanup, evidence, VPS synchronization, DMS non-regression, and tag exact-match are all mandatory for GO.
