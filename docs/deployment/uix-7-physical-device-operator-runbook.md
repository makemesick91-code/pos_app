# UIX-7 — Physical-Device Operator Runtime Runbook (fresh current-descendant revalidation)

This is the **one human checkpoint** for UIX-7 GO closure: capturing **genuine,
operator-observed PHYSICAL-DEVICE evidence** for a fresh current-descendant
revalidation of the cashier runtime scenarios **R01–R06** and **R11–R20**, using
the release-equivalent APK built from the runtime app-source anchor. The runner
(`scripts/uix7_operator_runner.sh`) is fail-closed: it **never** fabricates a
PASS, and it is UIX-7-schema-native (top-level `scenarios` array with
`scenario_id` + `result`; `evidence_source` is persisted exactly as `physical`).

> This runbook does **not** create runtime evidence by itself, does **not** flip
> any scenario to PASS, does **not** change the decision to GO, and does **not**
> create or move any GO tag. Those happen only after a real device run and the
> closure gate.

## Why a full R01–R20 revalidation is required

The current UIX-7 manifest carries R01–R06 evidence bound to commit `4bc58a4`.
The Android application source has materially changed since `4bc58a4` (UIX-8 and
later Android work). The closure gate only accepts a PASS row whose `commit_sha`
equals the final `candidate_commit` **or** the `app_source_unchanged_since`
anchor. The safe closure model is therefore:

```
runtime app-source anchor      = 97fbb64
app_source_unchanged_since     = 97fbb64   (set by finalize, never current HEAD)
fresh physical PASS rows R01–R20 commit_sha = 97fbb64
final candidate_commit         = the future evidence-only closure merge commit
```

Historical R01–R06 evidence stays in Git history; the **final** UIX-7 manifest
must use fresh current-descendant evidence before GO. The staleness gate is not
weakened.

## Release-candidate binding

| Field | Value |
| --- | --- |
| Runtime app-source anchor | `97fbb64` (ancestor of current HEAD; HEAD may differ by evidence/tooling/docs commits) |
| APK | `android/app/build/outputs/apk/pilot/app-pilot.apk` (variant `pilot`, debug-signed) |
| APK SHA-256 | `1a83931bedd6c66366018d7562e674c620c6d1baa79273dcc102d1f633ce0564` |
| Package / version | `com.aishtech.poslite`, versionName `0.1.0` |
| Physical device | Xiaomi `2311DRK48G` (`duchamp`), Android 14 / API 34, 1220×2712 @ 480dpi |

Rebuild the APK or change runtime source ⇒ re-record the SHA-256 and re-run
evidence; evidence from a different APK is not reusable.

## Prerequisites (once)

```bash
# Build the release-equivalent pilot APK FROM THE ANCHOR (97fbb64) and record its SHA-256.
git worktree add /tmp/aish-uix7-anchor 97fbb64      # or check out the anchor in a clean tree
# ...assemblePilot in that tree..., then:
sha256sum android/app/build/outputs/apk/pilot/app-pilot.apk

# On the physical device: enable USB debugging, plug in, accept the RSA prompt.
adb devices                                          # note YOUR device serial
adb -s <SERIAL> install -r android/app/build/outputs/apk/pilot/app-pilot.apk
```

## 1. Open the run (validates serial + APK checksum + package + device + anchor)

```bash
export UIX7_OP_SERIAL=<your-device-serial>           # REQUIRED — never auto-selected
export UIX7_OP_APK_SHA256=1a83931bedd6c66366018d7562e674c620c6d1baa79273dcc102d1f633ce0564
export UIX7_OP_APP_SOURCE_COMMIT=97fbb64             # runtime app-source anchor
export UIX7_OP_EVIDENCE_SOURCE=physical
export UIX7_OP_OPERATOR="<your name/id>"
bash scripts/uix7_operator_runner.sh preflight
```

Preflight refuses to start unless: the serial is present and the device reports
`device` state (not `unauthorized`/`offline`/missing), the APK exists and its
SHA-256 matches, the package is installed, the device API is supported, and the
anchor is a real commit that is an ancestor of HEAD. It binds one `run_id` plus
one **offline** and one **online** shared `clientReference`, and records the
runtime anchor and current repository commit **separately**. Only a 12-char hash
of the serial is persisted — never the raw serial.

## 2. Capture each scenario (dependency-ordered)

```bash
bash scripts/uix7_operator_runner.sh record <scenario_id>
```

For each scenario the runner auto-captures a screenshot from **your explicit
serial** (`adb -s <serial> exec-out screencap`), then asks for
`PASS/FAIL/PENDING`, a **substantive** observation of what you actually saw, and
(where applicable) a transaction reference, the shared client reference, and a
sanitized DB reference. It **downgrades to PENDING** (never a fabricated PASS) if
the observation is blank/generic, the screenshot is missing/empty/non-PNG, a
transaction row lacks its references, or a DB-required row lacks its DB proof; it
**rejects outright** a secret pasted into the observation, a mismatched
`clientReference`, a mismatched `run_id`, an unknown or protected scenario id
(H01–H04/Q01), or an unmet dependency.

Capture the chains **in order** so dependencies are satisfied:

- **Online:** `R01 → R02 → R03 → R04 → R05 → R06`
- **Offline:** `R11 → R12 → R13 → R14 → R15 → R16 → R17`
- **Accessibility/stability:** `R18 → R19 → R20`

Scenario coverage (exact names/classifications from the manifest):

| ID | Scenario | Txn ref | DB proof |
| --- | --- | --- | --- |
| R01 | Device activation / registration binding | – | – |
| R02 | Tenant/outlet/role binding (no admin/owner, no cross-tenant) | – | – |
| R03 | Online transaction — exactly one backend txn | ✓ | ✓ |
| R04 | Financial total parity (cart=subtotal=grand=receipt) | ✓ | – |
| R05 | Stable `client_reference` minted and reused | ✓ | ✓ |
| R06 | Double-submit protection (rapid tap → ≤1 txn) | ✓ | ✓ |
| R11 | Offline durable save (cart cleared only after durable save) | ✓ | – |
| R12 | Process-kill restoration (pending txn survives force-stop) | ✓ | – |
| R13 | Reconnect + idempotent sync | ✓ | ✓ |
| R14 | SYNCED only after server acknowledgement | ✓ | ✓ |
| R15 | Idempotency proof (one logical txn → sales=1, payments=1) | ✓ | ✓ |
| R16 | Receipt current-transaction binding (no stale result) | ✓ | – |
| R17 | Receipt/history/backend parity | ✓ | ✓ |
| R18 | Accessibility (TalkBack, focus, targets, 130% font, error TTS) | – | – |
| R19 | No crash / no ANR in the test window | – | – |
| R20 | No cleartext / no secret/token/PII/QR-payload in logs | – | – |

Discipline for the harder rows:

- **R11–R17** must share one `run_id`, one APK, one physical device, one logical
  offline transaction, and the single **offline** `clientReference`. **R03–R06**
  form one separate online chain sharing the **online** `clientReference`, under
  the same campaign `run_id`.
- **R15** DB proof must show `sales = 1`, `payments = 1`,
  `sale_items = <expected>`, `duplicate sales = 0`, `duplicate payments = 0`.
- **R18** requires genuinely enabling TalkBack + 130% font and observing spoken
  labels, lived focus order, touch targets, and that status is not colour-only —
  a UIAutomator dump alone cannot PASS.
- **R19** is a bounded window with filtered logcat (`FATAL EXCEPTION`, ANR, crash
  loops, unbounded WorkManager retry). **R20** is a sanitized review proving no
  cleartext endpoint / `10.0.2.2` / trust-all TLS / `Authorization` header /
  bearer/refresh/access token / password / customer PII / payment secret / QR
  payload.

## 3. Database proof (scoped, read-only, sanitized)

On the VPS pilot DB, scope by tenant/outlet/cashier/device/`clientReference`
over a bounded window and store only sanitized aggregates in the evidence dir —
never credentials, never a full dump. Provide the aggregate string as the DB
reference for R03, R05, R06, R13, R14, R15, R17.

## 4. Review and finalize

```bash
bash scripts/uix7_operator_runner.sh status      # captured scenarios so far
bash scripts/uix7_operator_runner.sh finalize    # merge genuine PASS rows into the manifest
```

`finalize` copies only genuine session rows into `manifest.scenarios`, writing
**only UIX-7 schema fields**, setting `evidence_source=physical` and
`commit_sha=97fbb64` (the anchor), and setting `app_source_unchanged_since` to
the anchor. It sets `candidate_commit` **only** when you supply a real
`UIX7_OP_CANDIDATE` (the final evidence-closure commit) — never current HEAD
automatically. The decision stays `NO-GO — GO DEFERRED` unless every recordable
row is PASS **and** a real closure candidate is bound. H01–H04 and Q01 are left
untouched.

## 5. Only then: the closure gate + GO tag (out of scope for this tooling)

After a genuinely complete run, with UIX-7 debt closed or a valid waiver in
place and local/origin/VPS exact-match, run the fail-closed closure gate:

```bash
UIX7_CLOSURE_GATE_MODE=closure UIX7_CI_GREEN=true UIX7_PR_MERGED=true \
UIX7_EXACT_MATCH=true bash scripts/uix7_runtime_closure_gate.sh
```

Create the annotated `uix-7-android-cashier-experience-remediation-go` tag
**only after** this gate PASSes. Absence of proof = NO-GO. Prior GO tags are
immutable.
