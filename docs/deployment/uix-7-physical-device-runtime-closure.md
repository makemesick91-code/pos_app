# UIX-7 — Physical-Device Runtime Closure & GO Evidence

Canonical evidence record for the UIX-7 Android Cashier physical-device runtime
closure (UIX7-R052..UIX7-R070). Machine-validated by
`scripts/uix7_runtime_closure_gate.sh` (structural always; closure assertions
under `UIX7_CLOSURE_GATE_MODE=closure`).

On-device evidence is operator-captured on a real physical device and is never
fabricated, and never substituted with emulator or unit-test evidence
(UIX7-R062). Every `PENDING` row is a live blocker: UIX-7 stays **NO-GO** until
it is a physical-device `PASS` with a reference.

- GO tag (must not exist until closure): `uix-7-android-cashier-experience-remediation-go`
- Source candidate under test: `4bc58a4` (main; merge of PR #61 — foundation + online-idempotency fix)
- Pilot APK (physical device): `aishpos-uix7-pilot-4bc58a4.apk`, sha256 `43b5d599a7e5a1865d8adf1a594218d7feca914f37f76e13f3e19d76dac6e795`, endpoint `https://aishpos.online/` only (no `10.0.2.2`), allowBackup=false, NSC present, debug-signed.

Evidence references must not contain: password, bearer token, cookie, refresh
token, device secret, private key, raw QRIS payload, real customer PII, or DB
credentials (UIX7-R063).

## Runtime-closure defect found & fixed (this session)

FINDING-01 (P1 / GO-blocker, UIX7-R054/R055): the Android online "Bayar Tunai"
checkout posted to `/api/v1/sales` with **no `client_reference`**, so the
backend dedup could not protect it. A read timeout on the real device — with the
cart kept and the cashier retrying — could create a duplicate sale on a
timeout-after-commit. Root cause + fix are code-confirmed; the entire backend
idempotency chain already existed and only the online client had opted out.
Fixed client-side (`CashierViewModel` mints/reuses a stable `client_reference`;
`SalesRepository` sends it). Regression tests: backend
`SalesIdempotencyTest::test_online_cash_sale_with_reference_is_idempotent_on_retry`
+ Android wire tests. Merged (PR #61 → `4bc58a4`), authoritative CI green,
reconfirmed on the physical device (online sale id carried `client_reference`
`6595f9c6…`; 3 tap attempts on one cart → exactly one sale). See
`FINDING-01-online-idempotency` in the runtime-closure evidence set.

## Device metadata

| Field | Value |
| --- | --- |
| Physical device (GO evidence) | Xiaomi 2311DRK48G (Redmi Note 13 Pro), Android 14 / API 34, arm64-v8a, 1220x2712 @480dpi, wireless ADB |
| Emulator (functional validation only — NOT GO evidence) | Pixel_6a AVD, Android 14 / API 34, x86_64 |

## Runtime verification matrix

Result ∈ {PASS, N/A, PENDING, FAIL}. A `PASS` is physical-device-verified with a
reference. `PENDING` rows here were **functionally validated on the emulator**
(no defect found) but still require a physical-device mirror for GO (UIX7-R062).
A `FAIL` on any financial/durability/authorization/QRIS/leakage row is an
automatic NO-GO (UIX7-R069).

| # | Scenario | Rule(s) | Result | Evidence ref |
| --- | --- | --- | --- | --- |
| 1 | Physical device detected & authorized | UIX7-R062 | PASS | `adb devices -l` → 2311DRK48G, state device |
| 2 | Pilot APK verified (id/version/signature/endpoint) | UIX7-R049 | PASS | apk sha256 43b5d599…; only https://aishpos.online/; allowBackup=false |
| 3 | Pilot APK installed & launched | UIX7-R049 | PASS | adb install -r → Success; CashierActivity resumed |
| 4 | Authenticated Cashier session | UIX7-R001/R052 | PASS | device-activation.txt: active auth session exercised by an authenticated server sale sync |
| 5 | Device activation / registration binding (backend) | UIX7-R052 | PASS | device-activation.txt: registered_devices id=2, user 8, tenant 9, ACTIVE, app 0.1.0 |
| 6 | Tenant/outlet binding correct | UIX7-R052 | PASS | user 8 → tenant 9; sales bound store_id=1 (UIX7-PILOT Outlet) |
| 7 | Role restriction (no admin/owner, no cross-tenant) | UIX7-R052 | PASS | users.role=cashier, is_platform_admin=NULL; only tenant-9 products visible |
| 8 | Online transaction — exactly one backend txn | UIX7-R053 | PASS | online-transaction.txt: sale id=3, one row per checkout |
| 9 | Financial total parity (cart=subtotal=grand=receipt) | UIX7-R058 | PASS | online-transaction.txt: 15.000 cart=subtotal=grand=line; paid 20.000; change 5.000 |
| 10 | Double-submit protection (rapid tap → ≤1 txn) | UIX7-R054/R055 | PASS | online-transaction.txt: 3 taps on one cart → exactly 1 sale, one client_reference |
| 11 | Offline durable save (cart cleared only after save) | UIX7-R053/R056 | PENDING | emulator functional PASS (offline-durability.txt, screen E07); physical mirror pending R062 |
| 12 | Process-kill restoration (pending txn survives force-stop) | UIX7-R056 | PENDING | emulator functional PASS (offline-durability.txt, screen E08); physical mirror pending R062 |
| 13 | Reconnect + idempotent sync | UIX7-R054/R057 | PENDING | emulator functional PASS (offline-durability.txt, screen E09); physical mirror pending R062 |
| 14 | Sync acknowledgement → local SYNCED only after ack | UIX7-R057 | PENDING | emulator functional PASS (local draft SYNCED + serverSaleId set); physical mirror pending R062 |
| 15 | No duplicate transaction (retry/worker replay) | UIX7-R055 | PENDING | emulator functional PASS (re-sync: cref da452800 = 1 row); physical mirror pending R062 |
| 16 | QRIS created/awaiting not shown as paid | UIX7-R060 | N/A | qris.txt: no reachable QRIS UI in the cash-only cashier |
| 17 | QRIS confirmed/settled synthetic transition | UIX7-R061 | N/A | qris.txt: QRIS is a Sprint-31 backend concern, not exposed in the cashier |
| 18 | QRIS duplicate callback → one state transition | UIX7-R061 | N/A | qris.txt: not exposed in the cashier surface |
| 19 | QRIS failed/expired truthful | UIX7-R060/R061 | N/A | qris.txt: not exposed in the cashier surface |
| 20 | Receipt current-transaction binding (no stale result) | UIX7-R059 | PENDING | emulator functional PASS (success receipt showed current cart); physical mirror pending R062 |
| 21 | Receipt/history/backend parity | UIX7-R058 | PENDING | emulator functional PASS (accessibility-and-logs.txt, screen E10 Ringkasan reconciles); physical mirror pending R062 |
| 22 | Accessibility — TalkBack | UIX7-R064 | PENDING | labels present in tree; lived TalkBack announcement pending physical device R062 |
| 23 | Accessibility — focus order | UIX7-R064 | PENDING | emulator functional PASS (logical tree order); physical mirror pending R062 |
| 24 | Accessibility — semantic labels | UIX7-R064 | PENDING | emulator functional PASS (12/12 clickable labeled); physical mirror pending R062 |
| 25 | Accessibility — touch targets | UIX7-R064 | PENDING | emulator functional PASS (buttons ≥48dp; inputs 45dp minor note); physical mirror pending R062 |
| 26 | Accessibility — font scaling | UIX7-R064 | PENDING | emulator functional PASS (1.3× no clipping, screen E11); physical mirror pending R062 |
| 27 | Accessibility — error announcements | UIX7-R064 | PENDING | text-labelled states present; physical mirror pending R062 |
| 28 | No crash / no ANR | UIX7-R036 | PENDING | emulator functional PASS (0 FATAL/ANR); physical mirror pending R062 |
| 29 | No cleartext / no trust-all TLS at runtime | UIX7-R047 | PENDING | emulator functional PASS (0 cleartext/10.0.2.2); physical mirror pending R062 |
| 30 | No credential/token/PII/QR-payload in logs | UIX7-R063 | PENDING | emulator functional PASS (0 leakage; pilot no HTTP logging); physical mirror pending R062 |

## Emulator functional validation (NOT GO evidence — UIX7-R062)

Per the operator's direction, offline durability, process-kill restoration,
reconnect/idempotent-sync, receipt/history parity, accessibility, and log review
were exercised on the emulator against the real `aishpos.online` backend. All
passed with **no defect found**. This validates functionality but does not
satisfy the physical-device GO requirement; those rows remain `PENDING` above.

## Synthetic data cleanup (UIX7-R065)

Scoped strictly to the synthetic pilot fixture (tenant `UIX7-PILOT-01` id 9,
outlet `UIX7-PILOT-OUT-01`, cashier `cashier+uix7-pilot-01@tenant.local` id 8,
SKU prefix `UIX7-PILOT-`), guarded by a tenant-code check inside a DB
transaction, after a pre-cleanup backup
(`/root/aish_pos_pilot_backup_pre_uix7cleanup_20260714_030021.dump`). Fixture
(tenant/store/cashier/4 products) retained for the deferred physical-device GO pass.

| Item | Result | Evidence ref |
| --- | --- | --- |
| Synthetic transactions / lines removed | PASS | deleted sales=4, sale_items=7 (tenant 9); after=0 |
| Synthetic payments removed | PASS | deleted payments=4; after=0 |
| Synthetic device registrations removed | PASS | deleted registered_devices=3; after=0 |
| Synthetic sync queue / dead-letter cleared | PASS | tenant_android_sync_batches=0; no dead-letter |
| Synthetic stock movements resolved | PASS | inventory_movements=0 |
| Synthetic QRIS / payment intents removed | PASS | tenant_billing_payment_intents=0 |
| Emulator app + local draft/session wiped | PASS | adb uninstall com.aishtech.poslite → Success (0 packages) |
| Residual synthetic data verified absent | PASS | tenant-9 sales=0 devices=0 activations=0 sync_batches=0 payment_intents=0 |
| Temporary cashier credential | RETAINED | cashier/tenant/store/products kept for future physical-device GO pass |

## VPS synchronization & health

| Check | Result | Evidence ref |
| --- | --- | --- |
| local main = origin/main = VPS HEAD = final evidence commit | PENDING | synced after evidence PR merge |
| HTTPS root 200 / HTTP 301 / live 200 / ready 200 | PASS | preflight: 200 / 301 / ok / ok |
| storage/framework + bootstrap/cache owned www-data:www-data | PENDING | verify at VPS sync |

## DaengtisiaMS non-regression (UIX7-R043)

| Check | Result | Evidence ref |
| --- | --- | --- |
| DMS HEAD unchanged & worktree clean | PENDING | verify at closure |
| No DMS source/migration/ownership change | PENDING | Aish work touched only tenant-9 pilot DB rows + aish repo |

## Exact-match & tag (UIX7-R066/R070)

| Check | Result | Evidence ref |
| --- | --- | --- |
| Authoritative CI green for source candidate 4bc58a4 | PASS | PR #61 Authoritative summary gate: pass |
| Evidence CI green (evidence-only closure commit) | PENDING | evidence PR |
| local = origin = VPS = final evidence commit | PENDING | at closure |
| Prior GO tags unchanged (immutable) | PASS | no tag moved/deleted |
| Annotated UIX-7 GO tag peeled commit == final commit | PENDING | GO DEFERRED — not created |

## GO / NO-GO decision

Decision: **NO-GO — GO tag DEFERRED (UIX7-R062/R070).**

Rationale: the transaction / financial / idempotency core is physical-device
verified (rows 1–10 PASS), the GO-blocking online-idempotency defect is fixed,
merged, CI-green, and reconfirmed on the real device, and no defect remains.
However, offline durability, process-kill restoration, reconnect/sync,
receipt/history, and accessibility (rows 11–15, 20–30) were validated on the
**emulator only** and require a physical-device mirror before GO; UIX7-R062
forbids substituting emulator evidence, and the runtime-closure gate enforces
"no GO while a PENDING blocker remains." The annotated GO tag
`uix-7-android-cashier-experience-remediation-go` must not be created until those
rows are physical-device `PASS`. Synthetic data has been cleaned; the fixture is
retained for the deferred physical-device pass.
