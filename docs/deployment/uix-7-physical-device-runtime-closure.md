# UIX-7 — Physical-Device Runtime Closure & GO Evidence

Canonical evidence record for the UIX-7 Android Cashier physical-device runtime
closure (UIX7-R052..UIX7-R070). This document is machine-validated by
`scripts/uix7_runtime_closure_gate.sh`. Structural checks run always; the
closure assertions (no placeholder, every PASS carries an evidence reference, no
GO while a blocker remains) run under `UIX7_CLOSURE_GATE_MODE=closure`.

On-device evidence is **operator-captured on a real physical device** and is
never fabricated, and never substituted with emulator or unit-test evidence
(UIX7-R062). Every `PENDING` row below is a live blocker: until it is replaced
with an observed `PASS` plus an evidence reference, UIX-7 remains **NO-GO**.

- GO tag (must not exist until closure): `uix-7-android-cashier-experience-remediation-go`
- Baseline source commit under test: `fd7b037`
- Pilot APK: `aishpos-uix7-pilot-fd7b037.apk` — sha256 `e0354ce0c03a1a9c64b5f33f8409898cd4417e684b8d40ab606da38c2eff9e2d`
- Pilot API (baked into APK, verified): `https://aishpos.online/` (no `10.0.2.2`; `allowBackup=false`; networkSecurityConfig present)

Evidence references must not contain: password, bearer token, cookie, refresh
token, device secret, private key, raw QRIS payload, real customer PII, or
database credentials (UIX7-R063).

## Device metadata (physical device — UIX7-R062)

| Field | Value |
| --- | --- |
| Manufacturer / model | Xiaomi 2311DRK48G (Redmi Note 13 Pro) |
| Android version / API | 14 / 34 |
| ABI | arm64-v8a |
| Screen | 1220x2712 @ 480dpi |
| Connection | wireless ADB, authorized |

## Runtime verification matrix

Result column ∈ {PASS, FAIL, PENDING}. A `PASS` requires a concrete evidence
reference (screenshot path, sanitized log line, DB query result, API result, or
CI run). A `FAIL` on any financial/durability/authorization/QRIS/leakage row is
an automatic NO-GO (UIX7-R069).

| # | Scenario | Rule(s) | Result | Evidence ref |
| --- | --- | --- | --- | --- |
| 1 | Physical device detected & authorized | UIX7-R062 | PASS | preflight: `adb devices -l` → 2311DRK48G, state device |
| 2 | Pilot APK verified (id/version/signature/endpoint) | UIX7-R049 | PASS | apk-verification.txt: pkg com.aishtech.poslite v0.1.0, sha256 e0354ce0…, only https://aishpos.online/ |
| 3 | Pilot APK installed & launched | UIX7-R049 | PASS | adb install -r → Success; pid running |
| 4 | Authenticated Cashier login | UIX7-R001/R052 | PENDING | operator, on-device |
| 5 | Device activation record (backend) | UIX7-R052 | PENDING | operator + backend query |
| 6 | Tenant/outlet binding correct | UIX7-R052 | PENDING | backend query |
| 7 | Role restriction (no admin/owner surface, no cross-tenant) | UIX7-R052 | PENDING | operator + backend query |
| 8 | Online transaction — exactly one backend txn | UIX7-R053 | PENDING | operator + backend query |
| 9 | Financial total parity (cart=backend=receipt=history) | UIX7-R058 | PENDING | operator + backend query |
| 10 | Double-submit protection (rapid tap → one txn) | UIX7-R054/R055 | PENDING | operator + backend query |
| 11 | Offline durable save (cart cleared only after save) | UIX7-R053/R056 | PENDING | operator + app-db inspection |
| 12 | Process-kill restoration (pending txn survives force-stop) | UIX7-R056 | PENDING | operator + app-db inspection |
| 13 | Reconnect + idempotent sync | UIX7-R054/R057 | PENDING | operator + backend query |
| 14 | Sync acknowledgement → local SYNCED only after ack | UIX7-R057 | PENDING | operator + backend query |
| 15 | No duplicate transaction (retry/worker replay) | UIX7-R055 | PENDING | operator + backend query |
| 16 | QRIS created/awaiting not shown as paid | UIX7-R060 | PENDING | operator + backend query |
| 17 | QRIS confirmed/settled synthetic transition | UIX7-R061 | PENDING | synthetic callback + backend query |
| 18 | QRIS duplicate callback → one state transition | UIX7-R061 | PENDING | synthetic callback + backend query |
| 19 | QRIS failed/expired truthful | UIX7-R060/R061 | PENDING | operator + backend query |
| 20 | Receipt current-transaction binding (no stale result) | UIX7-R059 | PENDING | operator screenshots |
| 21 | Receipt/history/backend parity | UIX7-R058 | PENDING | operator + backend query |
| 22 | Accessibility — TalkBack | UIX7-R064 | PENDING | operator |
| 23 | Accessibility — focus order | UIX7-R064 | PENDING | operator + uiautomator dump |
| 24 | Accessibility — semantic labels | UIX7-R064 | PENDING | uiautomator dump |
| 25 | Accessibility — touch targets | UIX7-R064 | PENDING | operator + uiautomator dump |
| 26 | Accessibility — font scaling | UIX7-R064 | PENDING | operator screenshots |
| 27 | Accessibility — error announcements | UIX7-R064 | PENDING | operator |
| 28 | No crash / no ANR | UIX7-R036 | PENDING | sanitized logcat |
| 29 | No cleartext / no trust-all TLS at runtime | UIX7-R047 | PENDING | sanitized logcat |
| 30 | No credential/token/PII/QR-payload in logs | UIX7-R063 | PENDING | sanitized logcat |

## Synthetic data cleanup (UIX7-R065)

Cleanup is performed only after all runtime evidence is captured, scoped strictly
to the synthetic pilot fixture (tenant `UIX7-PILOT-01`, outlet
`UIX7-PILOT-OUT-01`, cashier `cashier+uix7-pilot-01@tenant.local`, SKU prefix
`UIX7-PILOT-`). Broad unscoped SQL deletion is forbidden.

| Item | Result | Evidence ref |
| --- | --- | --- |
| Synthetic cashier session revoked/deactivated | PENDING | — |
| Synthetic device deactivated / tokens revoked | PENDING | — |
| Synthetic transactions / lines removed or retained-and-documented | PENDING | — |
| Synthetic payments / QRIS records removed | PENDING | — |
| Synthetic sync queue / dead-letter cleared | PENDING | — |
| Synthetic stock movements resolved | PENDING | — |
| Temporary credentials rotated/removed | PENDING | — |
| Residual synthetic data verified absent | PENDING | — |

## VPS synchronization & health

| Check | Result | Evidence ref |
| --- | --- | --- |
| local main = origin/main = VPS HEAD = final evidence commit | PENDING | — |
| HTTPS root 200 / HTTP 301 / live 200 / ready 200 | PENDING | — |
| nginx / php8.5-fpm / queue / scheduler / PostgreSQL healthy | PENDING | — |
| storage/framework + bootstrap/cache owned www-data:www-data | PENDING | — |

## DaengtisiaMS non-regression (UIX7-R043)

| Check | Result | Evidence ref |
| --- | --- | --- |
| DMS HEAD unchanged (`8b0bb6a`) & worktree clean | PENDING | — |
| DMS HTTP healthy / DB SELECT 1 / php8.3-fpm active | PENDING | — |
| No DMS source/migration/ownership change | PENDING | — |

## Exact-match & tag (UIX7-R066/R070)

| Check | Result | Evidence ref |
| --- | --- | --- |
| Authoritative CI green for final source candidate | PENDING | — |
| Main integrity smoke green | PENDING | — |
| Evidence CI green (if evidence-only final commit) | PENDING | — |
| local = origin = VPS = final evidence commit | PENDING | — |
| Prior GO tags unchanged (immutable) | PENDING | — |
| Annotated tag peeled commit == final commit | PENDING | — |

## GO / NO-GO decision

Decision: **NO-GO — physical-device runtime verification not yet performed.**

Rationale: rows 4–30, cleanup, VPS sync, DMS non-regression, and exact-match are
`PENDING` operator-captured on-device evidence. Per UIX7-R070 all are mandatory
for GO; per UIX7-R062 none may be fabricated or substituted. The annotated GO
tag `uix-7-android-cashier-experience-remediation-go` must not be created while
any blocker above remains.
