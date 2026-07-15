# ADR 0009 — UIX-8C-07 Premium Authentication, Device Activation, Settings & Session Recovery

- Status: Accepted
- Date: 2026-07-15
- Sprint: UIX-8C-07 (Android cashier `com.aishtech.poslite` + Laravel backend)
- Rules: UIX8C-R211..R250 (`.claude/rules/61-android-cashier-full-premium-delivery-foundation.md`)
- Supersedes: nothing. Extends ADR 0004–0008 and rules 55/56/57/58/59.

## Context

Before UIX-8C-07 the Android cold-start path was a **token-only router**
(`MainActivity` → `CashierActivity` if a non-blank token exists, else
`LoginActivity`). There was no device-activation gate, no device-revocation
enforcement at startup, no session-expiry (401) handling anywhere in the OkHttp
stack, no Settings screen, and logout cleared **only the bearer token** — leaving
the tenant-scoped Room database (including unsynced offline sales), device UUID,
catalog cursors, printer settings, and WorkManager untouched, with no
pending-unsynced guard. The token was persisted in **plain SharedPreferences**
(an explicit `TODO(secure-storage)`). The backend already had a Sprint-34
device-activation/revocation domain, but its "poll my validity" path
hard-403s revoked devices (no reason), and the short-lived-code issuance
(`DeviceActivationService::prepare()`) had no callers.

These gaps make several failure modes possible: a revoked device silently
continuing to operate, a logout destroying unsynced revenue, cross-tenant data
bleed on account switch, an infinite/racy startup, and stale "connected"/"synced"
status. UIX-8C-07 closes them.

## Decision

### 1. One deterministic startup/auth state machine
Introduce a pure, framework-free `core/startup/StartupCoordinator` that consumes
`StartupInputs` and emits a single `BootState` (sealed) — `Bootstrapping`,
`DatabaseMigration`, `RestoringRuntime`, `ActivationRequired`, `ActivatingDevice`,
`LoginRequired`, `Authenticating`, `Ready`, `OfflineReady`, `SessionExpired`,
`DeviceInvalid`, `DeviceRevoked`, `ContextMismatch`, `RecoveryRequired`,
`RecoverableFailure`, `FatalFailure`. `StartupActivity` renders progress and
routes on the emitted state. The evaluation order is fixed and testable; `Ready`
requires installation + activation + not-revoked + tenant + outlet + session +
cashier-authorization + data-partition ALL valid (UIX8C-R211/R212/R213). Startup
is bounded by a timeout (UIX8C-R215) and never flashes login when a session is
restorable (UIX8C-R216). Connectivity is never treated as reachability
(UIX8C-R214): the revoked check runs only when the server was actually reached.

### 2. Device trust = two gates + server-authoritative status
Device activation (`POST /api/v1/android/device/activate`) and cashier login
(`POST /api/v1/auth/login`) are distinct gates (UIX8C-R217). A **new additive**
backend endpoint `GET /api/v1/android/device/status` returns a truthful posture
(`active` / `revoked` + `revocation_reason`, plus safe tenant/outlet/device
labels) and — unlike heartbeat — is reachable by a revoked device so the app can
learn *why* it is blocked (UIX8C-R221). Revoked/invalid devices fail closed with
no bypass (UIX8C-R220).

### 3. Keystore-backed secure storage (no new dependency)
Replace plain-prefs token storage with `core/session/SecureTokenStore`, which
encrypts the token (and the app-generated installation id) with an
`AndroidKeyStore` AES/GCM key via `javax.crypto`. We deliberately do **not** add
`androidx.security:security-crypto` (deprecated, and absent from the offline
Gradle cache — a build-reproducibility risk); the platform Keystore + a small,
JVM-testable cipher abstraction give the same guarantee with zero new
dependencies (UIX8C-R218/R219). The legacy plain-prefs token is migrated forward
and its plaintext copy deleted on first secure read.

### 4. One runtime-context source of truth + tenant isolation fail-closed
`core/runtime/RuntimeContext` holds the validated {tenant, outlet, cashier,
device, session, installation, appBuild}. All identity is server-derived
(UIX8C-R222/R223). Any tenant mismatch fails closed into `ContextMismatch` with an
audit-safe diagnostic (UIX8C-R226/R227). `core/session/LocalDataCleaner`
classifies every local store as global/device/tenant/outlet/cashier/transaction
scoped and performs an ordered, compensating cleanup on switch/reset
(UIX8C-R224/R235/R236/R237). An automated `CrossTenantCleanupTest` proves Tenant B
cannot read Tenant A artifacts (UIX8C-R228).

### 5. Unsynced-transaction safety + session recovery
`core/session/LogoutGuard` blocks logout/account-switch while
`OfflineSaleRepository.pendingCount()/failedCount()` report un-acked work
(UIX8C-R229/R230/R231), surfacing count + reason + "Sync sekarang" + safe retry
(UIX8C-R232). A `core/session/SessionEventBus` + OkHttp `AuthEventInterceptor`
turns a backend 401 into a `SessionExpired` event that locks the UI and preserves
same-tenant pending work for post-re-login resume (UIX8C-R233); a revoked device
quarantines the pending queue (UIX8C-R234).

### 6. Process restoration reuses the UIX-8C-06 identity
Restoration derives truth from Room (UIX8C-R241) and reuses the existing stable
`clientReference`/receipt identity for idempotency (UIX8C-R240) — no parallel
transaction/receipt identity, no duplicate sale/payment on process death
(UIX8C-R238). Raw credential input, activation tokens, half-submitted payments,
and stale success/connected status are never restored (UIX8C-R239).

### 7. Truthful Settings surface
`feature/settings/SettingsActivity` presents Account/Context, Device,
Application, Connection, Sync, Printer, and Security/Session sections over
canonical repositories/status, rendering "Tidak tersedia" for unknowns and never
the token/secret/activation-code/raw-encryption-id (UIX8C-R245/R246/R247). Status
derives from actual sources and is never colour-alone (UIX8C-R242/R243/R244).

## Alternatives considered
- **Keep plain prefs / add jetpack-security** — rejected: plaintext token violates
  UIX8C-R219; jetpack-security is deprecated and not cached (build risk).
- **Enforce activation/revocation only inside per-screen ViewModels** — rejected:
  reproduces the scattered-decision anti-pattern (UIX8C-R211).
- **Expand the owner/admin web console to issue device codes** — rejected as scope
  creep against the read-only owner boundary (rule 25); we add a hidden CLI
  provisioning command instead, mirroring `tenant:owner-provision`.
- **Allow logout to drop unsynced sales with a warning** — rejected: financial
  loss (UIX8C-R229); logout is blocked until synced.

## Consequences
- Startup is deterministic, testable, bounded, and revocation-aware.
- Secrets are Keystore-protected; no new dependency; reproducible offline build.
- Cross-tenant isolation and unsynced safety are enforced and test-proven.
- Backend change is additive/reversible (one endpoint, three nullable columns,
  one CLI, throttle+audit); `SaleService`/financial behaviour is unchanged.
- This closes none of the UIX-7/UIX-8 physical runtime debt: UIX-7 stays
  `NO-GO — GO DEFERRED`, UIX-8 stays `IMPLEMENTATION COMPLETE — GO DEFERRED`; a
  fresh physical revalidation remains mandatory after code freeze (UIX8C-R249/R250).
