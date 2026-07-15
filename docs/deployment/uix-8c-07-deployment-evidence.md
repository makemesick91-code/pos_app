# UIX-8C-07 — Deployment & Sprint GO Evidence

Premium authentication, device activation, settings & session recovery. This is a
**scaffold** — every field marked `<TBD-EVIDENCE>` is filled by the post-merge,
evidence-only PR against the fully tested candidate. No value here is fabricated;
absence of proof stays NO-GO (UIX8C-R030/R250).

## Summary

- Sprint: **UIX-8C-07** — Premium authentication, device activation, settings & session recovery
- Implementation status: **IMPLEMENTATION COMPLETE — physical validation deferred**
- UIX-7: **NO-GO — GO DEFERRED**
- UIX-8: **IMPLEMENTATION COMPLETE — GO DEFERRED**

## Baseline

- Baseline `origin/main` at sprint start: **`1e37a93`** (UIX-8C-06 evidence closure)
- UIX-8C-06 runtime source anchor: `3f9abe1`
- Previous sprint tag: `uix-8c-06-premium-receipt-history-printer-failure-states-go`
  (object `84ea09b` → peeled `1e37a93`) — immutable, untouched.

## Implementation

- Implementation branch: `feature/uix-8c-07-premium-auth-device-settings-session-recovery`
- Implementation PR: **`<TBD-EVIDENCE>`** (#NN)
- Implementation candidate SHA: **`<TBD-EVIDENCE>`** (full 40-hex)
- Implementation merge commit / runtime source anchor: **`<TBD-EVIDENCE>`** (full 40-hex)
- Authoritative full CI (exact candidate SHA `<TBD-EVIDENCE>`): run **`<TBD-EVIDENCE>`**
  — **`<TBD-EVIDENCE: SUCCESS>`** (classify → full CI; Android all-variant,
  foundation/design/CI-architecture gates, backend full suite + governance smoke,
  security; evidence lane skipped; authoritative summary PASS)

## Scope delivered

- `BootState` (sealed) + pure `StartupCoordinator` over `StartupInputs`; bounded
  startup, no login-flash when restorable, connectivity ≠ reachability.
- `RuntimeContext` single source of truth (tenant/outlet/cashier/device/session/
  installation/appBuild); two device-trust gates (`device/activate`, `auth/login`) +
  server-authoritative `GET /android/device/status`.
- `SecureTokenStore` (AndroidKeyStore + AES/GCM, no Jetpack Security dep, legacy
  plain-prefs migration); app-generated installation id (never IMEI/serial/MAC).
- `LocalDataCleaner` scope classification + ordered/compensating cross-tenant cleanup;
  fail-closed tenant isolation; unsynced logout/switch/reset gate; revoked quarantine.
- Premium splash / activation / login / session-expired / revoked / settings surfaces.
- Rules `UIX8C-R211..R250` (rule 61, PROJECT_RULES, foundation doc, CLAUDE.md), ADR
  `<TBD-EVIDENCE: 0009>`.
- Fail-closed gate `scripts/uix8c_auth_device_session_gate.sh` (+ self-test), wired
  into the authoritative CI foundation lane.
- Backend `GET /android/device/status` endpoint + regression fences
  (`DeviceStatusEndpointTest`, `DeviceActivationProvisioningTest`,
  `Uix8c07DeviceLifecycleTest`).

## Verification (candidate `<TBD-EVIDENCE>`, CI run `<TBD-EVIDENCE>`)

- Android unit tests (debug/pilot/release): **`<TBD-EVIDENCE: PASS all three variants>`**.
- All-variant Android build (`assemble{Debug,Pilot,Release}`) + lint
  (`lint{Debug,Pilot,Release}`): **`<TBD-EVIDENCE: PASS>`**.
- Backend full suite: **`<TBD-EVIDENCE>` passed / `<TBD-EVIDENCE>` failed**
  (`<TBD-EVIDENCE>` assertions), incl. the new `DeviceStatusEndpointTest`,
  `DeviceActivationProvisioningTest`, `Uix8c07DeviceLifecycleTest`.
- Foundation/gate chain: `verify_application_foundation_rules.sh`,
  `uix8c_foundation_gate.sh`, `uix8c_design_system_gate.sh`,
  `uix8c_cashier_catalog_cart_gate.sh`, `uix8c_offline_cash_durability_gate.sh`,
  `uix8c_payment_offline_sync_ux_gate.sh`, `uix8c_receipt_history_printer_gate.sh`,
  `uix8c_auth_device_session_gate.sh` (+ all self-tests) — **`<TBD-EVIDENCE: PASS>`**.

## APK metadata

- Variant: **`<TBD-EVIDENCE: pilot>`** (`assemblePilot`, debug-signed, `aishpos.online`)
- versionName / versionCode: **`<TBD-EVIDENCE>` / `<TBD-EVIDENCE>`**
- Package: `com.aishtech.poslite`
- APK SHA-256: **`<TBD-EVIDENCE>`**
- Source-traceable to candidate SHA: **`<TBD-EVIDENCE>`**
- Confirmed governed pilot HTTPS API URL present, no emulator alias: **`<TBD-EVIDENCE>`**

## Deployment (shared VPS) & DMS non-regression

- Deployment method: governed Git fast-forward (`git merge --ff-only origin/main`);
  Android + governance + docs + backend endpoint/test → **`<TBD-EVIDENCE:` migration
  y/n `>`** (device/status is read-only; note if any migration applies).
- Deployment commit (local = origin = VPS `/var/www/aish-pos`): **`<TBD-EVIDENCE>`**
- Aish pre-deploy HEAD: `1e37a93` → post-deploy HEAD: **`<TBD-EVIDENCE>`**; worktree
  clean before and after.
- Aish health: `/` = **`<TBD-EVIDENCE: 200>`**, `/health/live` = **`<TBD-EVIDENCE: 200>`**,
  `/health/ready` = **`<TBD-EVIDENCE: 200>`**.
- Runtime smoke (authenticated, synthetic):
  - `GET /api/v1/android/device/status` active → **`<TBD-EVIDENCE>`**
  - `POST /api/v1/android/device/activate` (valid) → **`<TBD-EVIDENCE>`**
  - `POST /api/v1/auth/login` (valid) → **`<TBD-EVIDENCE>`**
  - device revoke → `device/status` = revoked, cashier locked → **`<TBD-EVIDENCE>`**
  - cross-tenant isolation (tenant A token cannot read tenant B) → **`<TBD-EVIDENCE>`**
- Aish services active: nginx, php8.5-fpm (`aish-pos`), postgresql,
  `aish-pos-queue-worker` — **`<TBD-EVIDENCE>`**.
- Runtime ownership `www-data:www-data`; root-owned runtime files under
  `storage/framework` + `bootstrap/cache` = **`<TBD-EVIDENCE: 0>`**; pending
  migrations = **`<TBD-EVIDENCE>`**.
- DaengtisiaMS non-regression: HEAD **`8b0bb6a`** unchanged before and after, worktree
  clean; php8.3-fpm / nginx / postgresql / `daengtisiams-queue-worker` active. **DMS
  unaffected — `<TBD-EVIDENCE: PASS>`.**

## Emulator runtime evidence (labelled emulator)

Hardware-independent rows captured on a controlled emulator, bound to the candidate
SHA + APK SHA-256. **Emulator evidence stays labelled emulator; it is never
relabelled physical (UIX8C-R249, UIX7-R075).**

| Row | Scenario | Evidence source | Result |
|-----|----------|-----------------|--------|
| E1 | Cold start → activation → login → Ready | emulator | `<TBD-EVIDENCE>` |
| E2 | Session-expired (401) → re-auth, unsynced preserved | emulator | `<TBD-EVIDENCE>` |
| E3 | Revoked device lock (no bypass via back/restart) | emulator | `<TBD-EVIDENCE>` |
| E4 | Account switch cross-tenant cleanup, isolation holds | emulator | `<TBD-EVIDENCE>` |
| E5 | Process restart restores no-duplicate transaction | emulator | `<TBD-EVIDENCE>` |
| E6 | Secure token round-trip + legacy migration | emulator | `<TBD-EVIDENCE>` |

### Font-130% screenshot references (operator-observed, deferred)

- Splash / activation / login at 130%: **`<TBD-EVIDENCE: screenshot ref>`**
- Session-expired / revoked at 130%: **`<TBD-EVIDENCE: screenshot ref>`**
- Settings at 130%: **`<TBD-EVIDENCE: screenshot ref>`**

On-device large-font (100/115/130%) and TalkBack rows require an explicit
operator-observed human PASS and are **never fabricated** (UIX8C-R248/R250). Until
captured on a real device after code freeze they stay PENDING.

## Sprint GO tag

- Target: `uix-8c-07-premium-auth-device-settings-session-recovery-go`
- Evidence PR: **`<TBD-EVIDENCE>`** (post-merge deployment evidence).
- Evidence-only diff proof (no runtime/dependency/schema change vs candidate):
  **`<TBD-EVIDENCE>`**.
- Final exact-match (local = origin = VPS = tagged peeled commit): **`<TBD-EVIDENCE>`**.
- Final tagged (evidence) commit: **`<TBD-EVIDENCE>`** — a docs/evidence-only
  descendant of the runtime source anchor (exact hash recorded in the annotated tag
  message).

## Closure statement

UIX-8C-07 premium authentication, device-activation, settings, and session-recovery
implementation `<TBD-EVIDENCE: PASS>`. Identity is server-derived and resolved once
into `RuntimeContext`; device trust is two distinct gates plus a server-authoritative
status kill-switch; the cashier token is Keystore-encrypted; cross-tenant cleanup is
ordered, fail-closed, and isolation-tested; unsynced transactions are never lost to a
logout. The sprint-scoped tag confirms source remediation + automated verification
only and **never asserts UIX-7/UIX-8 runtime closure** (UIX8C-R002/R250). Historical
physical evidence (`run-97fbb64-2af94aa`, R11/R18) stays verbatim. Fresh physical
auth / activation / revoked-device / session-recovery / large-font / TalkBack
validation remains mandatory after final code freeze and final pilot APK generation.
UIX-7 remains `NO-GO — GO DEFERRED`; UIX-8 remains `IMPLEMENTATION COMPLETE — GO
DEFERRED`.
