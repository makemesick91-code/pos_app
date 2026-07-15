# UIX-8C-06 — Deployment & Sprint GO Evidence

Premium receipt, transaction history & printer failure states. This document is
finalized by the post-merge evidence-only PR; fields not knowable before
deployment are marked `TO BE FINALIZED BY POST-MERGE EVIDENCE PR` until then.

## Summary

- Sprint: **UIX-8C-06** — Premium receipt, transaction history & printer failure states
- Implementation status: **IMPLEMENTATION COMPLETE — physical validation deferred**
- UIX-7: **NO-GO — GO DEFERRED**
- UIX-8: **IMPLEMENTATION COMPLETE — GO DEFERRED**

## Baseline

- Baseline `origin/main` at sprint start: `949f1a1` (UIX-8C-05 evidence closure)
- UIX-8C-05 runtime source anchor: `8505ab5`
- Previous sprint tag: `uix-8c-05-premium-cash-payment-offline-sync-recovery-go`
  (object `69acc32` → peeled `949f1a1`) — immutable, untouched.

## Implementation

- Implementation branch: `feature/uix-8c-06-premium-receipt-history-printer-states`
- Implementation PR: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`
- Implementation candidate SHA: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`
- Implementation merge commit / runtime source anchor: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`
- Authoritative full CI (exact SHA): `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`

## Scope delivered

- `ReceiptProjection` + `ReceiptIdentity` + pure `ReceiptProjector` (local + server
  → one whole-rupiah parity type); premium `ReceiptActivity` = receipt + detail +
  reopen/reprint; identity-guarded ViewModel; one-shot print `Event`.
- `TransactionHistoryReconciler` (one row per logical transaction; merge/dedup/
  conflict); `HistoryDisplayState`; row → detail navigation.
- `PrinterCoordinator` (non-financial, concurrency-guarded) + typed `PrinterFailure`
  / `PrintOutcome`; bounded timeout, connect/write split, catch-all; least-privilege
  permissions (no `BLUETOOTH_SCAN`).
- Rules `UIX8C-R171..R210` (rule 61, PROJECT_RULES, foundation doc, CLAUDE.md).
- Fail-closed gate `scripts/uix8c_receipt_history_printer_gate.sh` (+ self-tests),
  wired into the authoritative CI foundation lane.
- Backend regression fence `Uix8c06ReceiptHistoryParityTest` (no backend source
  change).

## Verification

- Android unit tests (debug): PASS locally — `TO BE FINALIZED` (CI totals).
- All-variant Android build (debug/pilot/release) + lint: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`.
- Backend targeted parity fence: PASS (3 tests, 21 assertions).
- Backend full suite: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`.
- Foundation/gate chain (`verify_application_foundation_rules.sh`,
  `uix8c_foundation_gate.sh`, `uix8c_receipt_history_printer_gate.sh` + self-test,
  prior UIX-8C gates): `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`.

## Deployment (shared VPS) & DMS non-regression

- Deployment commit (local = origin = VPS): `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`
- Aish health (`/`, `/health/live`, `/health/ready`): `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`
- Services (nginx / php8.5-fpm `aish-pos` / postgresql / `aish-pos-queue-worker`): `TO BE FINALIZED`
- Runtime ownership `www-data:www-data`, root-owned runtime files = 0, pending migrations = 0: `TO BE FINALIZED`
- DaengtisiaMS HEAD unchanged (`8b0bb6a`), worktree clean, php8.3-fpm/nginx/postgres/queue active: `TO BE FINALIZED`

> UIX-8C-06 is Android + governance + docs + a backend **test-only** fence. No
> backend source/schema/dependency change → the VPS sync is a fast-forward Git
> sync with no migration/composer/cache-rebuild step.

## Sprint GO tag

- Target: `uix-8c-06-premium-receipt-history-printer-failure-states-go`
- Final tagged (evidence) commit: `TO BE FINALIZED BY POST-MERGE EVIDENCE PR`

## Closure statement

UIX-8C-06 premium receipt, transaction-history, and printer failure-state
implementation PASS. Receipt and history reuse canonical transaction identity and
whole-Rupiah values. Printer remains outside financial transaction authority.
Historical physical evidence remains unchanged. Fresh physical receipt / history /
printer / TalkBack / 130%-font validation remains mandatory after final code freeze
and final pilot APK generation. UIX-7 and UIX-8 remain GO deferred.
