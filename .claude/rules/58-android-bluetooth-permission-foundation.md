# 58 — Android Bluetooth Permission Foundation (FIX-BT-SCAN)

Permanent, enforceable foundation for **every** Android Bluetooth integration in
the cashier app (`com.aishtech.poslite`). Introduced by
FIX-ANDROID-BLUETOOTH-SCAN-PERMISSION-LINT after the Bluetooth ESC/POS printer
transport (`BluetoothPrinterConnection`) tripped an Android lint
`MissingPermission` error: it called `BluetoothAdapter.cancelDiscovery()` — a
`@RequiresPermission(BLUETOOTH_SCAN)` API on Android 12+ (API 31) — while the app
deliberately declares no `BLUETOOTH_SCAN` permission and never performs
discovery. The fix removed the stray scan-only call (least-privilege root cause,
not a suppression) and hardened the permission contract.

This rule **extends and never weakens** rules 55 (UIX7-R001..R080), 56
(UIX8-R001..R048), and 57 (UIX8B-R001..R100). Business truth and transaction
authority stay in the backend `App\Services\*` domains and the app's canonical
repositories/managers; the printer transport presents and streams only.

## Permission contract & Android version handling
- **BTPERM-R001** — Every protected Bluetooth API call MUST have an explicit,
  single permission owner; no protected call may run with an ambiguous owner.
- **BTPERM-R002** — Runtime permission checks MUST reflect the Android API level
  (a version gate on `Build.VERSION.SDK_INT >= Build.VERSION_CODES.S`).
- **BTPERM-R003** — Android 12+ (API 31) Bluetooth permissions MUST NOT be
  requested or required on older APIs.
- **BTPERM-R004** — Legacy permission behavior MUST remain compatible with every
  supported API (minSdk 26 … API 30 rely on install-time `BLUETOOTH` /
  `BLUETOOTH_ADMIN`, capped at `maxSdkVersion="30"`).
- **BTPERM-R005** — `BLUETOOTH_SCAN` and `BLUETOOTH_CONNECT` MUST NOT be treated
  as interchangeable; a `BLUETOOTH_CONNECT` call MUST NOT be guarded by a
  `BLUETOOTH_SCAN` check, or vice versa.
- **BTPERM-R006** — Low-level Bluetooth transport classes MUST NOT silently
  trigger a UI permission request; permission-request ownership belongs to the
  caller/UI layer, and the transport validates and returns a typed state.
- **BTPERM-R007** — Permission denial MUST return an actionable, typed result
  state (here: a distinct `PrintResult.Failure` message), never a bare boolean
  or opaque throw.
- **BTPERM-R008** — Permission denial MUST NOT crash the application.
- **BTPERM-R009** — A protected Bluetooth API MUST NOT be called after permission
  denial; the deny-by-default gate runs before the adapter is even resolved.
- **BTPERM-R010** — `SecurityException` MUST be handled defensively (safe typed
  failure) but MUST NOT be used to hide a permission defect or mask a missing
  check.

## Suppression & manifest discipline
- **BTPERM-R011** — Blanket `@SuppressLint("MissingPermission")` MUST NOT be used.
- **BTPERM-R012** — A scoped suppression MAY be used only when it is (a) at the
  narrowest possible scope, (b) documented with the guaranteed invariant, (c)
  guarded by a real runtime permission check, and (d) covered by a regression
  test. Suppression is never a substitute for a permission check.
- **BTPERM-R013** — Manifest Bluetooth permissions MUST remain least-privilege:
  a permission is declared only if a reachable code path uses it.
- **BTPERM-R014** — Location permission (`ACCESS_FINE_LOCATION` /
  `ACCESS_COARSE_LOCATION`) MUST NOT be added solely to silence Bluetooth lint;
  a discovery flow that legitimately needs it must be justified and, on API 31+,
  prefer `android:usesPermissionFlags="neverForLocation"` when location is not
  derived from scan results.

## Transaction isolation
- **BTPERM-R015** — Bluetooth printer failures MUST NOT affect transaction
  persistence (sale creation, offline queue, WorkManager sync, receipt storage).
- **BTPERM-R016** — Printer connectivity MUST remain outside financial
  transaction authority; the printer transport never becomes a pricing, payment,
  QRIS, settlement, or sync engine.
- **BTPERM-R017** — A printing failure MUST NOT duplicate or roll back a
  completed sale; a print is attempted only after the backend-approved receipt
  is `printable`, and its outcome is presentation-only.

## Evidence discipline
- **BTPERM-R018** — Hardware printer evidence MUST remain labelled physical-
  hardware evidence (consistent with rule 55 UIX7-R062, R071..R080).
- **BTPERM-R019** — Emulator or unit-test evidence MUST NOT be represented as
  physical printer validation.
- **BTPERM-R020** — Every Bluetooth permission fix MUST ship with regression
  tests and lint verification.

## Release foundation
- **BTPERM-R021** — A printer lint fix MUST NOT be used to fabricate a UIX-8 (or
  any UIX) GO.
- **BTPERM-R022** — UIX-7 and UIX-8 closure debt MUST remain unchanged unless
  separately and genuinely closed; this fix never flips their status.
- **BTPERM-R023** — Prior GO tags MUST remain immutable.
- **BTPERM-R024** — The fix GO tag MUST be annotated.
- **BTPERM-R025** — Authoritative CI MUST run on the exact fix candidate commit.
- **BTPERM-R026** — Absence of physical printer runtime evidence MUST be stated
  honestly; it is never fabricated.
- **BTPERM-R027** — No backend or schema change SHOULD be introduced for a
  permission lint fix.
- **BTPERM-R028** — DaengtisiaMS MUST remain untouched (rule 80).
- **BTPERM-R029** — All Bluetooth permission foundation rules above are
  mandatory for every future Android printer/Bluetooth integration.

## Enforcement
- `scripts/verify_application_foundation_rules.sh` checks this rule file exists
  and that its rule IDs are persisted.
- Android lint (`lintDebug`, wired into the authoritative Android build lane)
  MUST stay green for `MissingPermission`; a `BLUETOOTH_SCAN`/`MissingPermission`
  regression is a blocking failure.
- Because `main` is not branch-protected, the discipline is enforced by rule and
  reviewer discipline; do not tag until every gate is genuinely met.
