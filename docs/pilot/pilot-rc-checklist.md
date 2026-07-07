# Pilot Release Candidate (RC) Checklist

Sprint 14 — Pilot Release Candidate & Operator UAT Foundation.

Canonical source of truth: [`docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md`](../foundation/POS_ANDROID_SAAS_FOUNDATION.md).
Governance: [`docs/PROJECT_RULES.md`](../PROJECT_RULES.md).

This checklist gates whether a build may be promoted to a **pilot release
candidate** and handed to operators for UAT. It is enforced by
`php artisan pilot:rc-check`. It does **not** authorize a production deploy.

> A pilot RC is a candidate for a controlled operator trial, not a production
> release. No signing keys, no automatic deployment, no real customer data.

## 1. Source of truth

- [ ] Foundation document present and unchanged for this RC scope.
- [ ] `docs/PROJECT_RULES.md` locks Sprint 0 → Sprint 14 runtime rules.
- [ ] Sprint 14 evidence doc present.

## 2. GO tag chain

- [ ] Sprint 0 → Sprint 13 GO tags exist in the repository history.
- [ ] `main` contains the latest merged sprint work.
- [ ] RC candidate commit identified (recorded in the RC evidence doc).

## 3. Backend tests

- [ ] `cd backend && php artisan test` passes (full suite, Sprint 0–14).
- [ ] No skipped critical tenant-isolation or business-flow tests.

## 4. Android CI

- [ ] `:app:assembleDebug` green in Sprint 14 CI.
- [ ] `:app:testDebugUnitTest` green in Sprint 14 CI.
- [ ] Package `com.aishtech.poslite`, `minSdk 26`, `targetSdk 35` intact.

## 5. Release readiness (Sprint 13)

- [ ] `php artisan production:readiness-check --json` runs without exposing secrets.
- [ ] `php artisan release:go-no-go --json` returns GO or WATCH (documented).

## 6. Backup / restore runbook

- [ ] `docs/release/backup-restore-runbook.md` present and reviewed.

## 7. Demo tenant onboarding

- [ ] Admin can onboard a demo tenant (`api/v1/admin/tenant-onboarding`).
- [ ] Demo data seed + reset guard verified against a demo tenant only.

## 8. Operator UAT readiness

- [ ] `docs/pilot/operator-uat-checklist.md` present.
- [ ] `docs/pilot/smoke-scenario-pack.md` present.
- [ ] `php artisan pilot:uat-summary --json` runs and reports scenario totals.

## 9. Issue register

- [ ] `docs/pilot/issue-register.md` present.
- [ ] All BLOCKER/CRITICAL issues resolved or explicitly accepted (see §RC decision).

## 10. No secrets / no forbidden files

- [ ] No `.env`, `*.apk`, `*.aab`, `*.keystore`, `*.jks`, or database dump tracked.
- [ ] No payment gateway secret in Android source.
- [ ] Pilot docs contain placeholders only — no real credentials.

## RC GO / WATCH / NO-GO criteria

| Decision | Meaning |
|----------|---------|
| **GO**   | All required checks PASS. No open BLOCKER/CRITICAL issues. Backend + Android CI green. Promote to operator pilot. |
| **WATCH**| Only non-critical warnings (e.g. release gate WATCH, open MAJOR issue). Pilot may proceed with documented risk notes and follow-up actions. |
| **NO-GO**| Any required check FAILs, any open BLOCKER/CRITICAL issue, backend tests failing, or Android CI red. Do not promote. |

Record the final decision in
[`docs/pilot/rc-go-watch-no-go-evidence.md`](rc-go-watch-no-go-evidence.md).
