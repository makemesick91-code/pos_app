# UIX-8C-04 — Deployment & Sprint GO Evidence

P1 Offline CASH Durability & Idempotent Recovery. Finalized in the evidence-only
PR `docs/uix-8c-04-post-merge-deployment-evidence`. All placeholders replaced with
real values (rules 59/90; UIX8C-R027/R028).

## 1. Source baseline

- Actual baseline (`origin/main` at sprint start): `b04a6ae` (UIX-8C-03 merge;
  no evidence-only PR followed it).
- Implementation branch: `fix/uix-8c-04-offline-cash-durability-idempotent-recovery`
- Implementation PR: **#73** — "UIX-8C-04: Fix durable offline CASH checkout and idempotent recovery"
- Implementation candidate SHA: `720f3e8cc98f87e99f67ba5460efb3a22d42ebaa`
- Authoritative full CI run (exact candidate SHA): run **29374414614** — **SUCCESS**
  (AISH POS Authoritative PR CI). Jobs: classify (full_ci), Foundation + design +
  CI-architecture gates, Android all-variant build + tests, Backend full suite,
  governance smoke, security scan, authoritative-summary — all ✓; strict
  evidence/docs lane skipped (not an evidence-only PR, legitimate).
- Implementation merge commit: `5063eb417f72badc81d6d72407cbd3e5ff38dbed`
- Runtime source anchor (implementation merge commit): `5063eb4`

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

## 4. Automated verification (authoritative CI green; local advisory)

- Android affected unit tests (classifier / repository / ViewModel / offline): **PASS** (local).
- Android full unit suite (debug/pilot/release): **202 tests / 0 failures / 0 errors** per variant (local + authoritative CI).
- Android `lintDebug`: **clean** (local + CI).
- Backend targeted (`OfflineCashDurabilityIdempotencyTest`): **PASS** (4 tests / 27 assertions).
- Backend full suite: **1520 tests / 0 failures / 44,521 assertions** (local + CI).
- Foundation / design / catalog-cart / offline-cash-durability gates + self-tests: **PASS** (local + CI).

## 5. VPS deployment (Aish only — `/var/www/aish-pos`)

- Pre-deploy VPS HEAD: `b04a6ae` (branch `main`, worktree clean).
- Deploy method: `git fetch` + `git merge --ff-only origin/main` (no manual copy).
- Migrations: none introduced (Android/docs/tests + backend test only) → not run;
  `migrate:status` pending = **0** before and after.
- Composer: `backend/composer.lock` hash `17174070b7218a5b` unchanged before/after →
  Composer not run.
- Backend cache rebuild: not required (no config/route/env change) → not run.
- Post-deploy commit (local = origin = VPS): `5063eb4` (worktree clean).
- Health (HTTPS `aishpos.online`): `/` **200** · `/health/live` **200** · `/health/ready` **200**.
- Services: nginx **active** · postgresql **active** · php8.5-fpm **active** · aish-pos-queue-worker **active**.
- Ownership: `storage/framework` + `bootstrap/cache` = `www-data:www-data`; root-owned runtime files = **0** before and after.

## 6. DaengtisiaMS non-regression (`/var/www/asia-dental-lab-v2`)

- HEAD before: `8b0bb6a` · HEAD after: `8b0bb6a` (**unchanged**).
- Worktree clean; php8.3-fpm **active** · nginx **active** · postgresql **active** · daengtisiams-queue-worker **active**.
- Regression: **none** — DMS untouched (UIX8BOPS-R014..R022; rule 80).

## 7. Evidence closure & sprint GO tag

- Evidence-only branch: `docs/uix-8c-04-post-merge-deployment-evidence`
- Evidence PR: **#74** (docs-only; classifier lightweight evidence lane).
- Evidence CI: **SUCCESS** (strict evidence/docs validation).
- Final evidence commit: recorded on merge (evidence-only descendant of the runtime source anchor `5063eb4`).
- Sprint GO tag: `uix-8c-04-offline-cash-durability-idempotent-recovery-go` (annotated;
  points at the final evidence commit; created only after all gates PASS).

## 8. Closure boundary (honest terminal state)

```
Source remediation and automated verification PASS.
Historical physical R11 remains FAIL for the old APK and old runtime source.
A fresh physical-device campaign on the future frozen final APK remains mandatory.
UIX-7 and UIX-8 remain GO deferred.
```

UIX-8C-04: IMPLEMENTATION GO · UIX-7: NO-GO — GO DEFERRED · UIX-8: IMPLEMENTATION
COMPLETE — GO DEFERRED.
