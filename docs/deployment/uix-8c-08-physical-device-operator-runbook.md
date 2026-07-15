# UIX-8C-08 — Physical Device Revalidation, Runtime Closure & Final GO — Operator Runbook

> **Status: PREP / DEVICE-PENDING.** This runbook is the operator-facing driver for the
> UIX-8C-08 physical campaign. **Nothing in it is a PASS.** No physical device is attached
> and no operator has attested. Every scenario below starts `PENDING`. UIX-7 stays
> `NO-GO — GO DEFERRED`; UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`. No GO tag
> may be created from this document.

---

## 0. Honesty preface (read first)

The final target — `UIX-7 RUNTIME CLOSED / GO`, `UIX-8 RUNTIME CLOSED / GO`,
`UIX-8C-08 GO TAGGED` — is only permitted after a **real physical device campaign** on a
**post-freeze APK** with **human operator attestation**. This runbook prepares that
campaign; it does not and cannot substitute for it.

- Emulator evidence is **never** relabelled physical (rule 55 UIX7-R075).
- The immutable failed run `run-97fbb64-2af94aa` (R01 PENDING, R11 FAIL, R18 FAIL) is
  **never** edited to PASS (UIX8C-R003). A fresh run gets a **new** run ID and a **new**
  manifest bound to a **new** post-freeze commit + APK SHA-256 (UIX8C-R004/R005/R024).
- Draft waiver **PR #68 is NOT used** as closure.

---

## 1. Baseline manifest (captured Phase 0, this session)

| Item | Value | Verified |
|---|---|---|
| `main` HEAD | `96897bc` | ✅ this session |
| Working tree | clean | ✅ |
| UIX-8C-07 GO tag peeled commit | `96897bc` | ✅ matches baseline |
| `uix-7-runtime-closure-go` | **absent** | ✅ (must not exist yet) |
| `uix-8-runtime-closure-go` | **absent** | ✅ (must not exist yet) |
| `uix-8c-08-…-final-go` | **absent** | ✅ (must not exist yet) |
| Immutable failed run record | `docs/deployment/uix-8c-physical-run-run-97fbb64-2af94aa.json` | ✅ present, unmodified |
| Draft waiver PR #68 | DRAFT / NOT APPROVED / NOT ACTIVE | ❑ re-verify at freeze |
| Android SDK / JDK | SDK `/home/fikri/Android/Sdk`, JDK 21 | ✅ (build-capable) |
| **Physical device** | `adb devices -l` → **empty** | ❌ **BLOCKS campaign** |
| DMS baseline SHA | `8b0bb6a` (expected) | ❑ re-verify at deploy |
| VPS AishPOS HEAD | `96897bc` (expected) | ❑ re-verify at deploy |

Baseline data to re-capture at code-freeze into the fresh manifest:
`candidate_commit`, `apk_sha256`, `apk signing fingerprint`, `pilot HTTPS endpoint`,
`device alias/model/Android version`, `run_id`.

---

## 2. Current-state audit — R11 / R18 / R19 / R20

**Source remediation for R11 and R18 already landed in prior sprints.** UIX-8C-08's job is
*physical revalidation*, not re-writing that source — unless a fresh physical run surfaces
a new regression.

### R11 — Offline CASH durable save (immutable record: **FAIL**, hardware_independent)
- **Canonical meaning:** under a governed transport failure the CASH sale must be durably
  saved *before* the cart clears; the failed run showed the sale at risk of loss
  (violates UIX8C-R012/R013/R014).
- **Source status: ADDRESSED (UIX-8C-04/05/06).** Anchors confirmed present:
  - `core/network/TransportFailureClassifier.kt` — only genuine transport/unavailability
    → offline-eligible; HTTP 4xx/409, TLS, serialization/unknown are **never** offline.
  - `data/repository/OfflineSaleRepository.kt` → `createOfflineCashSale(...)` atomic durable
    save, `SaveResult.Saved(localId, clientReference)`, enqueue `OfflineSyncStatus.PENDING`,
    `MAX_SYNC_ATTEMPTS = 5`, `findSaleWithItemsByReference`.
  - `data/local/dao/OfflineSaleDao.kt` → `findByClientReference(...)` idempotency lookup.
  - `feature/cashier/CashierViewModel.kt` → `checkoutCash` / `checkoutCashOffline` guarded,
    cart-clear-after-durable-save; stable `clientReference` via `referenceProvider`.
- **Owed by UIX-8C-08:** physical P05/P06/P07/P08/P09/P10 evidence with **two-layer proof**
  (local Room + backend) — see §6. Immutable FAIL row is **not** touched.

### R18 — Accessibility incl. 130% font (immutable record: **FAIL**, hardware_independent)
- **Canonical meaning:** primary workflow layout collapsed / clipped primary actions at
  130% font (violates UIX8C-R021); also TalkBack/focus/targets/error-TTS.
- **Source status: STRUCTURALLY ADDRESSED (UIX-8C-02 dual-scroll shell + later screens).**
- **Owed by UIX-8C-08:** physical **human-observed** font-130% (P23) + TalkBack (P24), plus
  IME (P25) and narrow-portrait (P26). Immutable FAIL row is **not** touched.

### R19 — No crash / no ANR in the test window (**no observation on record**)
- **Canonical meaning:** bounded window with filtered logcat (`FATAL EXCEPTION`, ANR,
  crash) — none permitted.
- **Status:** never recorded (catalog-defined only). **Owed:** fresh execution record
  during the campaign window (P01–P30 span) → §7 P19 procedure.

### R20 — No cleartext / no secret/token/PII/QR-payload in logs (**no observation on record**)
- **Canonical meaning:** cleartext-denied network config + secret-in-log inspection.
- **Source status:** cleartext denied for pilot/release; OkHttp logger redacts `Authorization`
  and runs debug-only. **Owed:** fresh execution record (logcat + manifest inspection) → §7 P20.

> R19/R20 have **no** prior physical status. They must get an explicit `PASS/FAIL/BLOCKED`
> in the fresh manifest — never left blank (prompt §12, §31).

---

## 3. Dependency / risk map (pre-change)

**Offline-CASH transaction spine (the R11 blast radius):**
```
CashierViewModel.checkoutCash / checkoutCashOffline
  → SalesRepository.submitCash (online attempt)
      → TransportFailureClassifier  ── governed transport failure only ──┐
  → OfflineSaleRepository.createOfflineCashSale (atomic durable Room save)│
      → OfflineSaleDao.findByClientReference (idempotency)               │
      → OfflineSyncStatus.PENDING                                        │
  → cart clears ONLY after SaveResult.Saved                             ◄┘
  → OfflineSalesSyncScheduler → OfflineSalesSyncWorker (bounded retry, network-constrained)
      → server ACK → status SYNCED (never before ACK)
  → ReceiptProjector/ReceiptProjection (current-txn binding)
  → TransactionHistoryReconciler (one row per logical txn)
```
**Device-trust / session spine (R-new physical scenarios P02, P13–P22):**
```
BootState/StartupCoordinator → RuntimeContextStore → DeviceStatus/DeviceStatusMapper (server-authoritative)
  → LogoutGuard (blocks logout while unsynced) → LocalDataCleaner (tenant-scoped) → SecureTokenStore (Keystore)
```
**Risk / regression surface to watch during campaign:**
- Money parsing: **do not** touch `RupiahMoney.parse` grouping semantics → resurrects the
  100× receipt bug (server DTO money uses `substringBefore('.')`, not `parse`).
- `OFFLINE_PENDING` must stay `OFFLINE_PENDING` until a valid server ACK (UIX8C-R231).
- `clientReference` is single-identity across online/offline/restart/reconnect/worker — **no
  new identity** for UIX-8C-08.
- Bounded retry cap (`MAX_SYNC_ATTEMPTS = 5`) — a poison row stays FAILED/visible, never
  silently dropped.

**Physical-test surface (what a device is actually required for):**
- hardware_dependent (physical REQUIRED): printer (P27), camera/barcode (P28), OEM
  background/power (P08 process-kill realism, P29).
- hardware_independent but **operator-observed** (physical human required, emulator not a
  substitute for the human check): font-130% (P23/R18), TalkBack (P24/R18).
- hardware_independent (emulator admissible in general, but this campaign captures on
  physical for closure): offline durability, process-kill restore, reconnect/sync,
  idempotency, receipt/history parity, crash/ANR/log inspection.

---

## 4. Device & environment prerequisites (operator to satisfy before we start)

1. Physical Android phone, USB-debugging ON, authorized — `adb devices -l` shows a
   **hardware serial**, not `emulator-*`.
2. Pilot backend reachable: `https://aishpos.online/` (live per VPS notes).
3. **Post-freeze pilot APK** built from the exact candidate commit (built in §Phase-4 of the
   campaign, not now). Signing = approved debug/pilot certificate (repo policy: the pilot
   variant is `signingConfigs.getByName("debug")`, documented as the approved installable
   pilot cert — re-confirm at freeze; if a distinct release keystore is later mandated,
   status = NO_GO until provided).
4. Optional hardware: BT ESC/POS printer (P27), scannable barcode/QR + working camera (P28).
   Absent → row `NOT_AVAILABLE`; §7 P27 notes whether that blocks GO.
5. Operator present for the two irreducibly-human checks (P23 font-130%, P24 TalkBack).

**Device fingerprint capture (I run this once the device is attached):**
```bash
adb devices -l
adb shell getprop ro.product.manufacturer
adb shell getprop ro.product.model
adb shell getprop ro.build.version.release   # Android version
adb shell getprop ro.build.version.sdk       # SDK int
adb shell wm size ; adb shell wm density
adb shell dumpsys battery | grep -i level
# device alias = sha256 of serial (raw serial NEVER published — UIX8C-R026/R127)
```

---

## 5. Safe network-failure induction (R11/R06 conditions) — DMS-safe

Reproduce transport/DNS failure **on the device only**, reversibly, never touching the VPS
host network or DMS:

- **DNS failure (closest to the original R11):** per-device Private DNS to an unresolvable
  host —
  ```bash
  adb shell settings put global private_dns_mode hostname
  adb shell settings put global private_dns_specifier invalid.aishpos.internal
  # revert:
  adb shell settings put global private_dns_mode off
  ```
- **Full transport loss:** `adb shell svc wifi disable ; adb shell svc data disable`
  (revert with `enable`).
- Induce failure **after** a valid authenticated session so the request reaches the
  classifier, matching the original scenario.
- **Never** modify VPS `/etc/hosts`, VPS DNS, firewall, or any DMS resource to simulate
  failure. Device-local only.

Document the chosen method per run in the manifest `notes`.

---

## 6. Two-layer offline durability proof (mandatory for R11 rows)

UI screenshots alone are **not** durability proof (prompt §18). Every offline-CASH row needs:

**Layer A — local (Room) proof.** Pilot is `isDebuggable=true`, so `run-as` works:
```bash
adb shell run-as com.aishtech.poslite sh -c \
 'sqlite3 databases/<room_db_file> "SELECT client_reference,sync_status,created_at,item_count,total_minor FROM offline_sales ORDER BY created_at DESC LIMIT 3;"'
```
(Exact db filename + columns confirmed from `LocalOfflineSaleEntity`/DAO before first run.)
Capture: `clientReference`, `sync_status` (must be non-ACK e.g. `PENDING`), created timestamp,
tenant/outlet reference, item count, whole-rupiah total. **Redact** customer-sensitive data.

**Layer B — backend proof.**
- *Before reconnect:* backend has **no** ACK for that `clientReference`.
- *After reconnect:* backend has **exactly one** logical transaction; ACK identity matches;
  the **same** local row flips to acknowledged; **no duplicate**.
- I drive backend checks over SSH to the VPS pilot DB (`aish_pos_pilot`) / authenticated
  API only — never DMS.

---

## 7. Physical scenario checklist (P01–P30)

Each row is `PENDING` until captured. Valid status: `PASS | FAIL | BLOCKED | NOT_APPLICABLE`.
A final GO requires **zero** `FAIL`/`BLOCKED`/`PENDING` on mandatory rows.
`R#` = canonical scenario mapped from the UIX-7 catalog; `(new)` = device-trust scenario
(UIX-8C-07 debt) with no legacy R-number.

| P | Maps to | Scenario | Evidence I auto-capture | Human? | Status |
|---|---|---|---|---|---|
| P01 | R01 | Fresh install + launch, no crash | `adb install -r -t`, `dumpsys package … versionName`, screenshot, logcat | | PENDING |
| P02 | (new) | Device activation binds tenant/outlet, code not stored, survives restart | screenshots, `run-as` prefs check (no raw code), backend audit | | PENDING |
| P03 | R02 | Cashier login, correct context, no creds in log | screenshots, `logcat` redaction check | | PENDING |
| P04 | R03/R04 | Online CASH → one backend txn, parity, receipt | screenshots, backend txn query, receipt capture | | PENDING |
| P05 | R11 | Offline CASH (network disabled) durable, pending, no false-synced | §6 Layer A, screenshots | | PENDING |
| P06 | R11 | **DNS/backend-failure CASH** (reproduce R11), durable `offline_sales`, pending until ACK | §6 Layer A + Layer B(before), screenshots | | PENDING |
| P07 | R11/R12 | Force-stop after offline sale → row survives, no dup | `am force-stop`, §6 Layer A after restart | | PENDING |
| P08 | R12 | **Genuine process-kill** recovery, one logical txn, pending preserved | `am force-stop` (genuine kill), re-launch, §6 Layer A | | PENDING |
| P09 | R13 | Reconnect + WorkManager sync → server ACK, row→acknowledged | re-enable net, `dumpsys jobscheduler`/work, §6 Layer B(after) | | PENDING |
| P10 | R15 | Duplicate protection: repeat retry/relaunch → one backend txn, stable clientRef | backend count query, §6 Layer A | | PENDING |
| P11 | R16 | Receipt reopen after restart, whole-rupiah parity (no 100× regression) | screenshots, compare to backend/Room | | PENDING |
| P12 | R17 | History reconciliation: one logical row, correct state | screenshots, reconciler check | | PENDING |
| P13 | (new) | **Backend-driven session expiry** → locked, pending preserved, re-login same tenant | backend expire (SSH/API), screenshots, §6 Layer A | | PENDING |
| P14 | (new) | **Backend-driven device revoke** → fail-closed, data hidden, queue protected | backend revoke, device status poll, screenshots | | PENDING |
| P15 | (new) | Revoked-device back-button no bypass | `input keyevent BACK`, screenshot | | PENDING |
| P16 | (new) | Revoked-device deep-link no bypass | `am start`/deep link, screenshot | | PENDING |
| P17 | (new) | Revoked-device restart no bypass | `am force-stop`+launch, screenshot | | PENDING |
| P18 | (new) | Revoked-device offline no bypass (revocation already known) | net off + restart, screenshot | | PENDING |
| P19 | R16/(new) | Logout blocked with pending txn, truthful count/reason, no deletion | screenshots, §6 Layer A unchanged | | PENDING |
| P20 | (new) | Cashier switch blocked with pending txn | screenshots, §6 Layer A unchanged | | PENDING |
| P21 | (new) | Cashier switch **after** ACK → prev state cleared, activation retained | screenshots, §6 Layer A empty | | PENDING |
| P22 | (new) | Cross-tenant isolation: old tenant data not visible, no cache leak/migration | tenant-A write → switch → tenant-B read (isolation test) | | PENDING |
| P23 | R18 | **Font scale 130%** — no clipping, CTAs reachable | screenshots @ `font_scale 1.30` | ✅ eyes-on | PENDING |
| P24 | R18 | **TalkBack** — labels, focus order, actionable, error TTS, not colour-only | screen recording | ✅ operator | PENDING |
| P25 | R18 | Keyboard/IME does not cover CTA (activation/login/payment) | screenshots with IME up | (✅) | PENDING |
| P26 | R18 | Narrow portrait layout usable, scroll available, no clip | screenshots | (✅) | PENDING |
| P27 | R27(hw) | Printer path (physical) | print test + receipt, failure recovery | hardware | PENDING |
| P28 | R28(hw) | Barcode/camera path (physical) | permission states, scan valid/invalid | hardware | PENDING |
| P29 | (new) | Background/foreground refresh after backend state change | `am` background/foreground, screenshots | | PENDING |
| P30 | R19-adj | Final restart sanity: activation/session/context correct, no dup, no crash loop | restart, screenshots, logcat | | PENDING |
| **R19** | R19 | **No crash / no ANR across the whole window** | `adb logcat -b crash` + filtered main dump over run window | | PENDING |
| **R20** | R20 | **No cleartext / no secret/token/PII/QR in logs** | full-window logcat scan + manifest secret scan | | PENDING |

**Per-scenario record (what I write into the manifest for each):** `scenario_id`,
`precondition`, `steps`, `expected`, `actual`, `status`, `timestamp`, `operator`,
`candidate_sha`, `apk_sha256`, `evidence_refs[]`, `notes`.

**Common auto-capture commands I run:**
```bash
adb logcat -c                                        # clear before a scenario
adb exec-out screencap -p > docs/evidence/uix-8c-08/<Pxx>_<slug>_<run-id>.png
adb shell screenrecord /sdcard/<Pxx>.mp4 & …         # for TalkBack / dynamic flows
adb logcat -d > docs/evidence/uix-8c-08/<Pxx>_logcat_<run-id>.txt
adb shell am force-stop com.aishtech.poslite         # genuine process kill (P07/P08/P17)
adb shell settings put system font_scale 1.30        # P23 (reset to 1.0 after)
```
Screenshots are named auditable, e.g. `P06_dns_failure_offline_cash_pending_<run-id>.png`,
`P13_backend_session_expired_<run-id>.png`, `P24_talkback_login_<run-id>.png`.

---

## 8. Backend-driven session / revocation (P13/P14) — I drive, DMS-safe

Uses the **actual** pilot backend, not a test double.
- **Session expiry:** revoke the device/session token in `aish_pos_pilot` (or via the
  Sanctum/auth mechanism), then trigger an Android request → expect `SessionExpired`, mutation
  blocked, pending txn preserved, re-login same tenant/outlet, no duplicate.
- **Device revoke:** mark the device revoked in the Sprint-34 device-activation domain →
  Android poll `GET /api/v1/android/device/status` returns revoked+reason → fail-closed.
- Capture backend audit event + authenticated endpoint result + Android state + local pending
  count. **Never** expose raw token. **Only** touch `aish_pos_pilot`; never DMS.

---

## 9. Evidence storage rules (prompt §26/§27)

- Fresh manifest: `docs/deployment/uix-8c-08-runtime-evidence.json` (scaffold below; all rows
  `PENDING`, `decision NO_GO`). It is a **new** file — the immutable failed-run record is
  never edited.
- Screenshots/logs/recordings under `docs/evidence/uix-8c-08/` with checksums.
- Large videos: store in approved evidence storage, commit checksum + reference only.
- **Never** commit: tokens, passwords, cashier PIN, activation secret, customer PII, full
  device serial, DB creds, signing secret.

---

## 10. What happens when you attach the device (resume path)

1. Operator satisfies §4; I run §4 fingerprint capture.
2. **Code freeze** on the implementation branch (only after any source fix + green local
   suite + exact-SHA CI). Build pilot APK from the exact candidate; hash SHA-256; fill the
   manifest header.
3. Deploy candidate to VPS (backup first; runtime-user cache; DMS bracket) — §Phase-6.
4. I drive P01–P30 + R19/R20, auto-capturing everything scriptable, keeping P23/P24 (and
   P27/P28 hardware) at `PENDING` until you attest.
5. You complete the operator attestation (`uix-8c-08-operator-attestation-template.md`).
6. Only if **every** mandatory row is `PASS` and both attestations are signed do the closure
   gates run; only if they return `decision == GO` are tags cut — on the final evidence
   commit, in order UIX-7 → UIX-8 → UIX-8C-08.

**Until then: `NO_GO`. UIX-7 `NO-GO — GO DEFERRED`. UIX-8 `IMPLEMENTATION COMPLETE — GO
DEFERRED`. WEBVIEW-1…6 / REL-1 / COMM-1 remain BLOCKED.**
