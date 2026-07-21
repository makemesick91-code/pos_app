# UIX-7 Time-Bounded Risk Waiver — DRAFT (FOR REVIEW ONLY)

> **THIS IS A DRAFT. IT IS NOT APPROVAL AND CARRIES NO SIGNATURE.**
> It does not close UIX-7, it does not declare any UIX-7 scenario PASS, it does
> not authorize a UIX-8 GO tag, and it MUST NOT be used to bypass the closure
> gate. It becomes effective only when the accountable owner explicitly approves
> it AND every "Mandatory closure condition" below is met. Until then UIX-8
> status remains `IMPLEMENTATION COMPLETE — GO DEFERRED`.

Prepared by: Claude Code (release engineering assist) — drafting only.
Governing rules: rule 55 (UIX7-R051/R062, R071..R080), rule 59
(UIX8BOPS-R071..R077), rule 90 (release/GO), UIX8-R044 / UIX8B-R092.

---

## 1. Waiver ID
`WAIVER-UIX7-001` (draft) — assign/confirm on approval.

## 2. Exact unresolved UIX-7 scenarios
Factual, from `docs/deployment/uix-7-runtime-evidence.json` (policy_version
1.0.0), app source unchanged since `4bc58a4`. **10 hardware-independent rows are
PENDING** and constitute the entire open debt:

| ID | Classification | Status | Scenario |
|----|----------------|--------|----------|
| R11 | hardware_independent | PENDING | Offline durable save (cart cleared only after durable local save) |
| R12 | hardware_independent | PENDING | Process-kill restoration (pending txn survives force-stop) |
| R13 | hardware_independent | PENDING | Reconnect + idempotent sync |
| R14 | hardware_independent | PENDING | Sync ack → SYNCED only after server ack |
| R15 | hardware_independent | PENDING | Idempotency proof (one logical txn → sales=1, payments=1) |
| R16 | hardware_independent | PENDING | Receipt current-transaction binding (no stale result) |
| R17 | hardware_independent | PENDING | Receipt / history / backend parity |
| R18 | hardware_independent | PENDING | Accessibility — labels, focus order, touch targets, font scaling, error announcements |
| R19 | hardware_independent | PENDING | No crash / no ANR in the test window |
| R20 | hardware_independent | PENDING | No cleartext / no secret/token/PII/QR-payload in logs |

## 3. Factual evidence status (no fabrication)
- **Resolved:** R01–R06 (hardware_neutral) = PASS (physical evidence).
- **Not applicable:** H01 (camera/scan), H02 (Bluetooth printer), H03 (NFC),
  H04 (OEM background restriction), Q01 (QRIS-not-shown-as-paid) = `N/A` —
  outside this waiver's scope; not counted as debt and not claimed as PASS.
- **Unresolved (this waiver's subject):** R11–R20 = **PENDING**. No genuine
  controlled-emulator evidence has been captured. Manifest `candidate_commit` is
  null; manifest decision is `NO-GO — GO DEFERRED`.
- **UIX-7 is NOT declared PASS by this document** (see §11).

## 4. Residual risk being accepted (if approved)
Cutting a UIX-8 GO while R11–R20 lack genuine runtime observation accepts the
risk that a regression in **offline durability, process-kill restoration,
idempotent sync/ack, receipt/history parity, accessibility, crash/ANR, or log
hygiene** ships without direct on-emulator confirmation. Impact class:
financial-correctness / transaction-loss / duplication and accessibility —
normally automatic NO-GO (UIX7-R069). Mitigating factors: these paths are
covered by JVM unit tests (money integrity, bounded retry, idempotency mapping)
and the app source is unchanged since `4bc58a4`; but unit tests are explicitly
**not** a substitute for runtime evidence (UIX7-R062).

## 5. Business rationale
_[Owner to complete.]_ Placeholder: time-boxed pilot enablement while a
controlled emulator observation window is scheduled; the UIX-8B screen/experience
and safety foundation are implementation-complete and CI-green, and the pilot
backend is deployed and healthy.

## 6. Mitigation
- Fail-closed operator runner (`scripts/uix8_operator_runner.sh`) staged to
  capture R11–R20-equivalent evidence the moment an AVD window opens.
- Pilot APK bound to candidate + SHA-256; no source drift permitted without
  rebuild + re-evidence (UIX8BOPS-R039/R064).
- Rollback point recorded (VPS `111799a` → current `main`); DMS isolated.
- Scope limited to the 10 listed rows; H-series and Q01 remain governed
  separately.

## 7. Monitoring
_[Owner to confirm cadence.]_ Placeholder: watch `/health/live`, `/health/ready`,
queue worker, and pilot sale/sync outcomes daily during the waiver window;
capture any offline-durability, duplicate-transaction, or sync-ack anomaly as an
immediate waiver-revoking incident.

## 8. Accountable owner
`OWNER: __________________________` (name / role) — **placeholder, unsigned.**

## 9. Approval
`APPROVED BY: __________________  DATE: __________  SIGNATURE: __________`
**— NOT SIGNED. This draft is not approval. Claude Code will not sign or approve
on the owner's behalf.**

## 10. Review / expiry date
- Effective from: `<date of explicit owner approval>`
- Expiry / mandatory review: `<to be set at approval — recommended ≤ 30 days from approval>`
- On expiry with debt still open: UIX-8 GO (if taken under this waiver) must be
  re-evaluated; the waiver does not auto-renew.

## 11. Statement — UIX-7 is NOT PASS
This waiver **does not** declare UIX-7 complete, closed, or PASS. R11–R20 remain
PENDING. The waiver accepts residual risk for a bounded period; it does not
convert absent evidence into evidence (UIX8BOPS-R071/R076, UIX7-R079).

## 12. Mandatory closure condition
Even if approved, this waiver obliges genuine closure: capture authentic
controlled-emulator evidence for R11–R20 (source-attributed, commit-bound,
APK-SHA-bound) and move the UIX-7 manifest to a real PASS **before** the expiry
date. The waiver is a time-box for closure, not a substitute for it.

## 13. Impact on the UIX-8 release
- A UIX-8 GO tag MAY be created **only** if: (a) all 21 UIX-8 runtime rows are
  genuinely operator-PASS, AND (b) this waiver is explicitly approved OR UIX-7 is
  genuinely closed, AND (c) `scripts/uix8_runtime_closure_gate.sh`
  (`UIX8_CLOSURE_GATE_MODE=closure`) PASSes, AND (d) authoritative CI is green on
  the exact candidate with local = origin = VPS exact-match, AND (e) DMS
  non-regression holds.
- This draft satisfies **none** of (a)–(e). With it in DRAFT state, UIX-8 stays
  `IMPLEMENTATION COMPLETE — GO DEFERRED`.

---
_To proceed after your review: reply with explicit approval (owner, dates,
rationale) and I will record the approved waiver as a governed artifact — but the
GO tag still waits on the operator runtime evidence and a passing closure gate._
