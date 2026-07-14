# UIX-8B-OPS-1 — Operator Runtime-Evidence Runbook (turnkey)

This is the **one human checkpoint** UIX-8B-OPS-1 could not automate: capturing
**genuine operator-observed** controlled-emulator evidence for the 21 UIX-8
runtime scenarios. Everything else (rules, verifier, pilot APK, VPS deploy, DMS
bracket, gates) is already done on this branch. The runner is fail-closed: it
**never** fabricates a PASS.

## Release candidate binding (already recorded in the manifest)
- Runtime candidate commit: `97fbb64b70a44ac5314c5169da19131b2280389e`
- APK: `android/app/build/outputs/apk/pilot/app-pilot.apk` (variant `pilot`, debug-signed)
- APK SHA-256: `1a83931bedd6c66366018d7562e674c620c6d1baa79273dcc102d1f633ce0564`
- Package `com.aishtech.poslite`, versionName `0.1.0`, versionCode `1`, targetSdk 35

If you rebuild the APK or the runtime source changes, re-record the SHA-256 and
re-run evidence — evidence from a different APK is not reusable (UIX8BOPS-R039).

## Prerequisites (once)
```bash
export JAVA_HOME=/home/fikri/.local/opt/android-studio/jbr
export ANDROID_HOME=/home/fikri/Android/Sdk
# Boot the controlled AVD (hardware-independent scenarios), then confirm:
adb devices          # must list one 'device'
adb install -r android/app/build/outputs/apk/pilot/app-pilot.apk
```

## 1. Open the run (validates candidate + APK checksum + emulator)
```bash
export UIX8_OP_APK_SHA256=1a83931bedd6c66366018d7562e674c620c6d1baa79273dcc102d1f633ce0564
export UIX8_OP_AVD=AishPOS_UIX7_API34     # your actual AVD name
bash scripts/uix8_operator_runner.sh preflight
```
This binds a single `run_id` and one shared `clientReference` for the whole
transaction chain, and refuses to start if HEAD ≠ candidate or the APK SHA-256
does not match.

## 2. Capture each scenario
```bash
bash scripts/uix8_operator_runner.sh record <scenario_id>
```
For each scenario the runner auto-captures a screenshot via `adb`, then asks for
`PASS/FAIL/PENDING`, a **substantive** observation of what you actually saw, and
(for transaction rows) a transaction reference. It **downgrades to PENDING** if:
the observation is blank/generic, the screenshot is missing, a transaction row
has no reference, or a dependency has not yet PASSed.

Capture the transaction chain **in order** so dependencies are satisfied:
`offline-checkout` → `force-stop-restoration` → `reconnect-sync` →
`idempotent-retry`, and `online-cash-checkout` → `receipt-parity` →
`history-parity`. All 21 rows in `docs/deployment/uix-8-runtime-evidence.json`
must be captured.

- Accessibility rows (`accessibility`, `font-scaling`) require actually turning
  on TalkBack / large font and observing — a UI-tree dump alone is not proof
  (UIX8BOPS-R051..R058).
- Idempotency PASS also needs the DB proof (§3) attached in the observation.

## 3. Database idempotency proof (scoped, read-only)
On the VPS pilot DB, scope by tenant/outlet/cashier/device/`clientReference`
and a bounded time window, and prove `sales=1`, `payments=1`,
`sale_items=<expected>`, `duplicate sales=0`, `duplicate payments=0`. Store only
sanitized aggregates in the evidence dir — never credentials or payloads.

## 4. Review and finalize
```bash
bash scripts/uix8_operator_runner.sh status     # see captured rows
bash scripts/uix8_operator_runner.sh finalize   # merges genuine PASS rows into the manifest
```
`finalize` sets the manifest decision to `GO` **only** when every mandatory row
is PASS **and** UIX-7 debt is closed or waived; otherwise it stays `GO_DEFERRED`.

## 5. UIX-7 debt (still open)
UIX-8 GO also requires UIX-7 closure debt to be genuinely closed or covered by a
valid, owner-approved, time-bounded waiver (UIX8BOPS-R071..R077). This is not
resolved by this runbook.

## 6. Only then: the closure gate + GO tag
```bash
UIX8_CLOSURE_GATE_MODE=closure UIX8_CI_GREEN=true UIX8_PR_MERGED=true \
UIX8_EXACT_MATCH=true UIX8_DMS_OK=true bash scripts/uix8_runtime_closure_gate.sh
```
Create the annotated `uix-8-android-cashier-premium-visual-transaction-experience-go`
tag **only after** this gate PASSes. Do not tag on absence of proof.
