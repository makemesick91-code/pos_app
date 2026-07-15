# UIX-8C-06 — Deployment & Sprint GO Evidence

Premium receipt, transaction history & printer failure states. Finalized by the
post-merge evidence-only PR.

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
- Implementation PR: **#77**
- Implementation candidate SHA: **`efc537f`** (`efc537f9f9431c956952b87e33583c64a8a482ec`)
- Implementation merge commit / runtime source anchor: **`3f9abe1`**
  (`3f9abe13bae8d6989c5594d6cce9a42777da9d5b`)
- Authoritative full CI (exact candidate SHA `efc537f`): run **29381595347** — **SUCCESS**
  (classify → full CI; Android all-variant, foundation/design/CI-architecture gates,
  backend full suite + governance smoke, security; evidence lane skipped;
  authoritative summary PASS)

## Scope delivered

- `ReceiptProjection` + `ReceiptIdentity` + pure `ReceiptProjector` (local + server
  → one whole-rupiah parity type); premium `ReceiptActivity` = receipt + detail +
  reopen/reprint; identity-guarded ViewModel; one-shot print `Event`.
- `TransactionHistoryReconciler` (one row per logical transaction; merge/dedup/
  conflict); `HistoryDisplayState`; row → detail navigation.
- `PrinterCoordinator` (non-financial, concurrency-guarded) + typed `PrinterFailure`
  / `PrintOutcome`; bounded timeout, connect/write split, catch-all; least-privilege
  permissions (no `BLUETOOTH_SCAN`).
- Rules `UIX8C-R171..R210` (rule 61, PROJECT_RULES, foundation doc, CLAUDE.md), ADR 0008.
- Fail-closed gate `scripts/uix8c_receipt_history_printer_gate.sh` (+ 14-case
  self-test), wired into the authoritative CI foundation lane.
- Backend regression fence `Uix8c06ReceiptHistoryParityTest` (no backend source change).

## Verification (candidate `efc537f`, CI run 29381595347)

- Android unit tests (debug/pilot/release): PASS (all three variants).
- All-variant Android build (`assemble{Debug,Pilot,Release}`) + lint
  (`lint{Debug,Pilot,Release}`): PASS.
- Backend full suite: **1525 passed / 0 failed** (45,180 assertions), incl. the new
  3-test / 21-assertion `Uix8c06ReceiptHistoryParityTest`.
- Foundation/gate chain: `verify_application_foundation_rules.sh`,
  `uix8c_foundation_gate.sh`, `uix8c_design_system_gate.sh`,
  `uix8c_cashier_catalog_cart_gate.sh`, `uix8c_offline_cash_durability_gate.sh`,
  `uix8c_payment_offline_sync_ux_gate.sh`, `uix8c_receipt_history_printer_gate.sh`
  (+ all self-tests) — PASS.

## Deployment (shared VPS) & DMS non-regression

- Deployment method: governed Git fast-forward (`git merge --ff-only origin/main`);
  Android + governance + docs + a backend **test-only** fence → no migration,
  composer, npm, or cache-rebuild step.
- Deployment commit (local = origin = VPS `/var/www/aish-pos`): **`3f9abe1`**
- Aish pre-deploy HEAD: `949f1a1` → post-deploy HEAD: `3f9abe1`; worktree clean
  before and after.
- Aish health: `/` = 200, `/health/live` = 200, `/health/ready` = 200.
- Aish services active: nginx, php8.5-fpm (`aish-pos`), postgresql,
  `aish-pos-queue-worker`.
- Runtime ownership `www-data:www-data`; root-owned runtime files under
  `storage/framework` + `bootstrap/cache` = **0**; pending migrations = **0**.
- DaengtisiaMS non-regression: HEAD **`8b0bb6a`** unchanged before and after,
  worktree clean; php8.3-fpm / nginx / postgresql / `daengtisiams-queue-worker`
  active. **DMS unaffected — PASS.**

## Sprint GO tag

- Target: `uix-8c-06-premium-receipt-history-printer-failure-states-go`
- Evidence PR: this PR (post-merge deployment evidence).
- Final tagged (evidence) commit: the merge commit of this evidence PR on `main`,
  a docs/evidence-only descendant of the runtime source anchor `3f9abe1`
  (exact hash recorded in the annotated tag message).

## Closure statement

UIX-8C-06 premium receipt, transaction-history, and printer failure-state
implementation PASS. Receipt and history reuse canonical transaction identity and
whole-Rupiah values. Printer remains outside financial transaction authority.
Historical physical evidence remains unchanged. Fresh physical receipt / history /
printer / TalkBack / 130%-font validation remains mandatory after final code freeze
and final pilot APK generation. UIX-7 and UIX-8 remain GO deferred.
