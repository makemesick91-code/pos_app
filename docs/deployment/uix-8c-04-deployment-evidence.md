# UIX-8C-04 — Deployment & Sprint GO Evidence

P1 Offline CASH Durability & Idempotent Recovery. This document is finalized in
the post-merge evidence-only PR (`docs/uix-8c-04-post-merge-deployment-evidence`);
fields depending on runtime facts are marked **TO BE FINALIZED BY POST-MERGE
EVIDENCE PR** until then and must carry real values before the sprint GO tag is
created (UIX8C-R027/R028; rules 59/90).

## 1. Source baseline

- Actual baseline (`origin/main` at sprint start): `b04a6ae` (UIX-8C-03 merge;
  no evidence-only PR followed it).
- Implementation branch: `fix/uix-8c-04-offline-cash-durability-idempotent-recovery`
- Implementation PR: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Implementation candidate SHA: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Authoritative full CI run (exact candidate SHA): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Implementation merge commit: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Runtime source anchor (implementation merge commit): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**

## 2. Original physical finding (immutable, unchanged)

- Run ID: `run-97fbb64-2af94aa`
- Old runtime source anchor: `97fbb64`
- Old APK SHA-256: `1a83931bedd6c66366018d7562e674c620c6d1baa79273dcc102d1f633ce0564`
- R01 PENDING · **R11 FAIL** · R18 FAIL — preserved verbatim, never flipped to
  PASS (UIX8C-R003/R129).

## 3. Remediation summary

- Root cause: no governed transport fallback on the online CASH path; a
  transport failure was surfaced as a hard error with 0 durable rows. See
  `docs/architecture/uix-8c-04-offline-cash-root-cause-analysis.md`.
- Fix: typed `TransportFailureClassifier`; governed online→offline CASH fallback;
  stable `clientReference` reuse; idempotent atomic durable Room save;
  cart-clear-after-durability; truthful offline-queued state. Backend unchanged
  (regression tests added).
- Rules: UIX8C-R096..R130 persisted across rule 61, `docs/PROJECT_RULES.md`, the
  foundation doc, and `CLAUDE.md`.
- Gate: `scripts/uix8c_offline_cash_durability_gate.sh` (+ self-tests), wired into
  `.github/workflows/_foundation-gates.yml`.

## 4. Automated verification (local, advisory; CI authoritative)

- Android affected unit tests (classifier / repository / ViewModel / offline):
  **PASS** (local run this sprint).
- Android full unit suite (debug/pilot/release variants): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR** (recorded from authoritative CI).
- Android lint (debug/pilot/release): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Backend targeted (`OfflineCashDurabilityIdempotencyTest`): **PASS** (4 tests / 27 assertions, local).
- Backend full suite: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Foundation / design / catalog-cart / offline-cash-durability gates + self-tests:
  **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**

## 5. VPS deployment (Aish only — `/var/www/aish-pos`)

- Pre-deploy VPS HEAD: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Deploy method: `git fetch` + `git merge --ff-only origin/main` (no manual copy).
- Migrations: none introduced by this sprint (Android-only source + backend
  tests) → migrations not run. **TO BE FINALIZED BY POST-MERGE EVIDENCE PR** (confirm no pending).
- Composer: dependencies unchanged → not run. **TO BE FINALIZED BY POST-MERGE EVIDENCE PR** (confirm).
- Post-deploy commit (local = origin = VPS): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Health: HTTPS root / `/health/live` / `/health/ready`: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Services (nginx, postgresql, php8.5-fpm `aish-pos`, `aish-pos-queue-worker`): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Ownership `www-data:www-data`, zero root-owned runtime files under
  `storage/framework` + `bootstrap/cache`: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**

## 6. DaengtisiaMS non-regression (`/var/www/asia-dental-lab-v2`)

- HEAD before / after (expected `8b0bb6a`, unchanged): **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Worktree clean; php8.3-fpm / nginx / postgresql / queue active: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Regression: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR** (expected: none; DMS untouched).

## 7. Evidence closure & sprint GO tag

- Evidence-only branch: `docs/uix-8c-04-post-merge-deployment-evidence`
- Evidence PR / evidence CI / final evidence commit: **TO BE FINALIZED BY POST-MERGE EVIDENCE PR**
- Sprint GO tag: `uix-8c-04-offline-cash-durability-idempotent-recovery-go`
  (annotated; points at the final evidence commit; created only after all gates
  PASS).

## 8. Closure boundary (honest terminal state)

```
Source remediation and automated verification PASS.
Historical physical R11 remains FAIL for the old APK and old runtime source.
A fresh physical-device campaign on the future frozen final APK remains mandatory.
UIX-7 and UIX-8 remain GO deferred.
```

UIX-8C-04: IMPLEMENTATION GO (upon completion) · UIX-7: NO-GO — GO DEFERRED ·
UIX-8: IMPLEMENTATION COMPLETE — GO DEFERRED.
