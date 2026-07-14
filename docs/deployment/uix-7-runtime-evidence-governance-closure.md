# UIX-7 — Runtime Evidence Governance Closure (mixed physical + emulator)

Current human-readable closure record for UIX-7 Android Cashier runtime
verification under **Android Runtime Evidence Governance policy v1.0.0**
(`docs/governance/android-runtime-evidence-governance.md`,
ADR `docs/adr/0001-authoritative-emulator-evidence-for-hardware-independent-runtime.md`).

The machine-parseable source of truth is
`docs/deployment/uix-7-runtime-evidence.json`, validated by
`scripts/uix7_runtime_closure_gate.sh` (preflight always; `closure` at the final
pre-tag check). The prior physical-only record
`docs/deployment/uix-7-physical-device-runtime-closure.md` is retained immutable
as history.

## Governance change (this PR)

- `UIX7-R062` revised: runtime evidence source is governed by scenario hardware
  classification. Hardware-dependent/OEM-specific → physical device required.
  Hardware-independent → controlled emulator evidence is authoritative when
  source-attributed, commit-bound, and auditable. Emulator evidence is never
  labelled physical.
- New rules `UIX7-R071..R080` and policy v1.0.0 formalise classification,
  admissibility, binding, anti-relabelling, and gate hardening.
- Closure gate rewritten to validate a structured manifest (not a bare `PASS`
  search), classification-aware and fail-closed, with 16 regression tests.

## Evidence sources

| Source | Meaning |
| --- | --- |
| PHYSICAL-DEVICE | Xiaomi 2311DRK48G (Redmi Note 13 Pro), Android 14 / API 34, arm64-v8a |
| AUTHORITATIVE EMULATOR | (to be captured) controlled AVD, app-supported API, release-equivalent pilot APK |
| AUTOMATED TEST | backend PHPUnit + Android unit/wire tests (CI) |
| DATABASE | scoped `aish_pos_pilot` queries (tenant 9) |
| CI | authoritative PR CI result |
| VPS | deployed-stack health + exact-match |

## Scenario classification & status

Full per-row detail is in the JSON manifest. Summary:

| Row(s) | Scenario | Classification | Source | Status |
| --- | --- | --- | --- | --- |
| R01–R06 | Activation, tenant/outlet/role, online txn, financial parity, stable client_reference, double-submit | hardware_neutral | PHYSICAL | **PASS** (retained) |
| R11–R17 | Offline durable save, process-kill restoration, reconnect, sync ack, idempotency, receipt binding, receipt/history parity | hardware_independent | EMULATOR (eligible) | **PENDING** capture |
| R18–R20 | Accessibility semantics, crash/ANR, cleartext/secret-in-log | hardware_independent | EMULATOR (eligible) | **PENDING** capture |
| H01–H03 | Camera/barcode, Bluetooth printer, NFC | hardware_dependent | PHYSICAL required | **N/A** (not in cash-only surface) |
| H04 | OEM background restriction | oem_specific | PHYSICAL required | **N/A** (verify on target OEM if relied upon) |
| Q01 | QRIS created ≠ paid | (n/a) | — | **N/A** (no QRIS UI; cash-only) |

## PHYSICAL-DEVICE EVIDENCE (retained)

Rows R01–R06 are physical-device verified against pilot APK sha256
`43b5d599…`, endpoint `https://aishpos.online/` only, at candidate `4bc58a4`.
The online-idempotency defect (FINDING-01) is fixed, merged, CI-green, and
reconfirmed on the physical device (3 taps on one cart → exactly one sale, one
`client_reference`). Detail: `uix-7-physical-device-runtime-closure.md`.

## AUTHORITATIVE EMULATOR EVIDENCE (to be captured)

Rows R11–R20 are now **admissible** on a controlled emulator under policy v1.0.0
but are **not yet captured**. They remain `PENDING` in the manifest — the policy
change is not retroactive (UIX7-R079) and this environment holds no genuine
emulator runtime artifacts. Capture requires: a documented AVD (name/API/ABI/
resolution/RAM), a release-equivalent pilot APK with recorded SHA-256 at the
final candidate commit, documented network state and process-kill method
(`adb shell am force-stop`), scoped DB verification of final sale/payment counts,
receipt/history/backend reconciliation, an accessibility inspection method, and a
bounded logcat window for crash/ANR/cleartext/secret review — all redacted.

## AUTOMATED TEST EVIDENCE

Backend `SalesIdempotencyTest` (online cash idempotency), Android wire/unit tests,
and the 16-case gate regression suite (`tests/ci/uix7_runtime_closure_gate_test.sh`)
are green in authoritative CI.

## DATABASE / VPS / CI EVIDENCE

Deferred to closure: local = origin = VPS exact-match, authoritative CI green for
the final candidate, and health checks — asserted to the `closure`-mode gate via
`UIX7_CI_GREEN` / `UIX7_PR_MERGED` / `UIX7_EXACT_MATCH`.

## GO / NO-GO decision

Decision: **NO-GO — GO DEFERRED.**

The governance unblock is complete: emulator evidence is now authoritative for
hardware-independent scenarios, the gate enforces it, and physical evidence is
retained. GO remains deferred because rows R11–R20 require genuine
controlled-emulator capture that has not yet been performed; fabricating it is
forbidden (UIX7-R062/R075/R079). When that evidence is captured into the
manifest, `candidate_commit` is set to the final commit, and CI/PR/exact-match are
true, `UIX7_CLOSURE_GATE_MODE=closure` will pass and the annotated GO tag
`uix-7-android-cashier-experience-remediation-go` may be created.
