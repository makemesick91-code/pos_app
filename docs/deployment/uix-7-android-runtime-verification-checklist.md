# UIX-7 — Android Cashier On-Device Runtime Verification (Operator Checklist)

**Why this is operator-performed:** the build/agent environment has no Android
SDK, emulator, or device (JDK 25). CI (JDK 21) builds the APK and runs unit
tests, but authenticated on-device runtime verification (GO items 45–53,
UIX7-R039) requires real hardware. The GO tag
`uix-7-android-cashier-experience-remediation-go` is **deferred** until the
evidence below is captured. Do not fabricate any result.

## 0. Prerequisites
- Approved physical device or hardware-accelerated emulator (minSdk 26+).
- Pilot artifact from CI (`assembleRelease` → `app-release-unsigned.apk`, sign
  locally with a disposable debug/pilot key) OR `assembleDebug` (`app-debug.apk`,
  already debug-signed and installable). Record: source commit, `applicationId`,
  `versionName`, `versionCode`, variant, SHA-256 (see §Artifact).
- Backend: `https://aishpos.online` (pilot). Release build points here by default
  (`BuildConfig.API_BASE_URL`); cleartext is denied by `network_security_config`.
- **Synthetic data only** — a disposable tenant, outlet, device, cashier, a few
  products, and test transactions provisioned through canonical services. Never
  real merchant/customer data. Never a real QRIS charge (use sandbox/test fixture).

## 1. Install & launch
- [ ] Install the recorded artifact; confirm package `com.aishtech.poslite`,
      versionName/versionCode match the record.
- [ ] Launch; no crash/ANR on cold start.

## 2. Auth, device & tenant context (UIX7-R001/R004/R027)
- [ ] Log in as the synthetic cashier over HTTPS; confirm traffic is TLS (cleartext
      to a non-dev host must fail — proves `network_security_config`).
- [ ] Device activation/heartbeat succeeds; tenant/outlet shown match the synthetic
      account. No `/admin` or `/owner` web capability is reachable from the app.

## 3. Online sale (UIX7-R008/R019/R023)
- [ ] Add products, edit quantities, checkout CASH online.
- [ ] Total/paid/change render via the canonical formatter (e.g. `Rp 25.000`).
- [ ] Receipt values equal the transaction detail; open receipt after a process
      restart and confirm it is still retrievable.

## 4. Offline sale → reconnect → sync (UIX7-R008/R009/R010/R011/R012)
- [ ] Enable airplane mode. Confirm offline catalog/cart still work.
- [ ] Create an offline CASH sale → UI shows the **draft/offline** state (not a
      final server receipt); cart clears only after the local save confirms.
- [ ] Kill the app process (swipe/force-stop), relaunch → the pending sale is still
      present (durable). **Nothing lost.**
- [ ] Re-enable network → sync runs → row becomes SYNCED only after server ack;
      pending count drops to 0.

## 5. Orphaned in-flight recovery (UIX7-R009 — the fixed bug)
- [ ] Create an offline sale; trigger sync, then kill the process **during** the
      in-flight attempt (before the server responds). Relaunch.
- [ ] Confirm the sale is recovered and syncs on the next run (not stranded), and
      the server shows exactly ONE transaction for that `clientReference`
      (idempotent — no duplicate).

## 6. Duplicate-submit protection (UIX7-R015/R025)
- [ ] On checkout, tap the pay button rapidly/repeatedly. Confirm exactly ONE
      server transaction is created.

## 7. QRIS lifecycle truthfulness (UIX7-R020/R021/R022) — sandbox only
- [ ] Start QRIS (online). Confirm "created / waiting" is NOT shown as paid/settled.
- [ ] Drive the sandbox/test fixture through waiting → verifying → paid; confirm each
      state is distinct and truthful. Expiry shows the expired state, cart intact.
- [ ] Offline: confirm QRIS is refused with the online-only message (no fabricated
      success).

## 8. State restoration (UIX7-R014)
- [ ] Build a cart, rotate the device → cart intact. (Process-death cart persistence
      is a documented deferred item — note current behavior honestly.)

## 9. Accessibility & performance spot-check (UIX7-R030/R031/R032/R033/R036)
- [ ] TalkBack reads product/cart/total/actions; status is conveyed by text, not
      colour alone. Font scaling large → primary actions & totals not clipped
      (phone and tablet).
- [ ] No main-thread jank/ANR during search/checkout/sync; no crash across the run.

## 10. Cleanup (UIX7-R040)
- [ ] Delete all synthetic transactions/devices/products/accounts via canonical
      services; verify removal (DB `SELECT` count = 0 for the synthetic tenant).

## Artifact record (fill from CI)
```
source_commit : <final release commit>
package_id    : com.aishtech.poslite
version_name  : 0.1.0
version_code  : 1
variant       : release (unsigned) / debug
sha256        : <from CI artifact>
built_by      : GitHub Actions uix7-ci / android-build-test (JDK 21)
```

## GO decision
GO only when §1–§10 pass with captured evidence (screens/logcat redacted of
tokens), authoritative CI is green, local == origin == VPS at the final commit,
DaengtisiaMS is unchanged (`8b0bb6a`), and previous GO tags remain immutable. Then
tag `uix-7-android-cashier-experience-remediation-go` (annotated) on the final
commit. Absence of this evidence = NO-GO.
