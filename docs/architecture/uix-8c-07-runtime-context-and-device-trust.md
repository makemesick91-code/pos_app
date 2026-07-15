# UIX-8C-07 — Runtime Context & Device Trust Architecture

One resolved identity, one source of truth. UIX-8C-07 collapses the scattered
"who am I / which tenant / which device / which token" reads into a single immutable
`RuntimeContext`, and formalises **device trust as two distinct gates** (device
activation and cashier authentication) over Keystore-backed secure storage.

This implements **UIX8C-R217..R228** (device trust two-gate, Keystore-backed
installation id/token, revoked fail-closed no-bypass, server-authoritative status
poll; one runtime-context source of truth, server-derived identity, no cross-tenant
cached identity, validated-not-raw cache; tenant-isolation fail-closed +
audit-safe diagnostic + automated isolation test). It extends — never weakens —
rules 55/56/57/58 and UIX8C-R001..R216.

## `core/runtime/RuntimeContext.kt` — the single source of truth

```kotlin
data class RuntimeContext(
    val tenant: TenantIdentity,       // id, name (display), server-derived
    val outlet: OutletIdentity,       // id, name, server-derived
    val cashier: CashierIdentity,     // id, name, authorized role
    val device: DeviceIdentity,       // activation id, status, last status check
    val session: SessionIdentity,     // token handle (opaque), issued/expiry
    val installation: InstallationId, // app-generated, stable, Keystore-backed
    val appBuild: AppBuild            // variant, versionName, versionCode
)
```

Every repository, sync worker, printer job, and navigation decision **derives** its
scope from this one object. There is no second place that answers "current tenant":
`CatalogRepository`, `OfflineSaleRepository`, `OfflineSalesSyncScheduler`,
`PrinterCoordinator`, and the nav host all take their tenant/outlet/cashier/device
scope from `RuntimeContext` (**UIX8C-R222**). The object is immutable; a change of
identity produces a **new** `RuntimeContext` via the cleanup pipeline below, never an
in-place mutation.

### Server-derived, never client-supplied (UIX8C-R223)

`tenant`, `outlet`, and `cashier` identity are populated **only** from
authenticated server responses (`auth/login`, `auth/me`, `device/activate`,
`device/status`). No route argument, intent extra, query string, header, cookie, or
hidden field may set or switch tenant/outlet (**UIX8C-R223**, mirrors UIX7-R005). A
client-supplied identifier is never trusted as authorization; it may only be
compared against the server-derived value for consistency (`tenantOutletConsistent`).

### Validated, not raw cache (UIX8C-R225)

A restored context is not trusted merely because it was found in storage. On
restore, `RuntimeContextLoader` validates the durable snapshot against the activation
record and, when online, against `device/status` and `auth/me`. A snapshot that
fails validation does not become a live `RuntimeContext`; it routes the boot machine
to `RecoveryRequired`/`ContextMismatch`. Cached identity is **evidence to be
re-validated**, never authority in itself (**UIX8C-R224/R225**).

## Device trust = two distinct gates (UIX8C-R217)

Device trust and cashier trust are **separate** and both required. Passing one never
implies the other.

### Gate 1 — device activation

- Endpoint: `POST /api/v1/android/device/activate` (existing Sanctum-tokened Android
  API; canonical `App\Services\*` device-activation domain).
- Binds authenticated cashier + tenant + outlet + **app-generated installation id** +
  activation state, sha256-hashing the activation token server-side (existing
  behaviour, rule 30 / UIX7-R052). Raw activation tokens are never stored or echoed.
- Produces the `device` portion of `RuntimeContext`.

### Gate 2 — cashier authentication

- Endpoint: `POST /api/v1/auth/login` (Sanctum token issue).
- Produces the `session` + `cashier` portions of `RuntimeContext`.
- A valid device with no valid session → `LoginRequired`; a valid session on a
  revoked/invalid device → locked (`DeviceRevoked`/`DeviceInvalid`). Neither gate
  is a bypass for the other.

### Gate 3 (continuous) — server-authoritative device status

A **new** read-only endpoint:

```
GET /api/v1/android/device/status
→ 200 { "status": "active" | "revoked", "reason": <string|null>, "checked_at": <iso8601> }
```

- Backed by the canonical device-lifecycle service; the app never computes device
  trust locally. `DeviceStatusRepository` polls it on boot and on resume, mapping the
  response through `DeviceStatusMapper` into `deviceRevoked` / `deviceStatusKnown`
  (**UIX8C-R221**, server-authoritative status poll).
- **Fail-closed on revocation (UIX8C-R219):** a `revoked` result immediately drives
  `BootState.DeviceRevoked` and quarantines the pending sync queue. Revocation is not
  bypassable by back-press, deep link, process restart, or going offline — the
  last-known server status is durable, and an offline device that was last seen
  revoked stays revoked. Only a governed re-activation clears it.
- Unknown/unreachable status when the device was previously **active** keeps the
  device usable offline (`OfflineReady`) but never upgrades an unknown to "healthy".

## Secure storage — `core/session/SecureTokenStore.kt`

- **AndroidKeyStore + AES/GCM.** A hardware-backed (where available) key in the
  `AndroidKeyStore` wraps an AES/GCM cipher used to encrypt the cashier token and the
  installation id at rest. GCM provides authenticated encryption (tamper-evident IV +
  tag). **No `androidx.security:security-crypto` (Jetpack Security) dependency** is
  added — the store is built directly on `KeyStore` + `Cipher` to avoid the
  deprecated/beta Jetpack Security surface and keep the dependency set minimal.
- **Legacy migration.** The prior plain-`SharedPreferences` token is migrated on first
  run of the new store: read the legacy plaintext value, re-encrypt into the Keystore
  store, then delete the legacy key. Migration is idempotent and one-way; after
  migration no plaintext token remains.
- **Never logged, never backed up.** Tokens and installation id are never written to
  logs, analytics, crash reports, or screenshots (UIX7-R026); `allowBackup=false`
  keeps them off cloud/adb backup (UIX7-R006).

### App-generated installation id (UIX8C-R218)

The installation id is a random app-generated UUID minted once on first launch and
stored in `SecureTokenStore`. It is **never** derived from IMEI, serial number, MAC
address, `ANDROID_ID`, advertising id, or any hardware/OS identifier — those are
privacy-sensitive, permission-gated, and non-portable. The installation id is the
stable device handle passed to activation and never leaves secure storage in
plaintext.

## Cross-tenant cache hygiene — `core/session/LocalDataCleaner.kt`

All local state is classified by scope so that an account/device switch clears
exactly the right partitions and nothing tenant-bearing survives into a new identity
(**UIX8C-R226/R235..R237**).

| Classification | Examples | On tenant/account switch |
|----------------|----------|--------------------------|
| **global** | app theme, language, feature flags (non-tenant) | keep |
| **device-scoped** | installation id, device activation record | keep (re-validated) |
| **tenant-scoped** | Room product/catalog cache, tenant settings, tenant Room DB | **clear / re-scope** |
| **outlet-scoped** | outlet catalog subset, outlet-specific config | **clear** |
| **cashier-scoped** | cashier prefs, recent searches, session token | **clear** |
| **transaction-scoped** | cart, in-flight payment state, printer job state | **clear** (after unsynced gate) |

Only `global` and `device-scoped` state survives a switch. Everything tenant-,
outlet-, cashier-, or transaction-scoped is torn down. A switch is **not** a device
re-activation (UIX8C-R235): the device-scoped installation id and activation are
preserved and re-validated, while tenant-bearing caches are destroyed.

### Tenant-isolation fail-closed rule (UIX8C-R226)

If the cleaner cannot positively prove that all tenant-scoped partitions for the
outgoing identity were cleared, it refuses to build the new `RuntimeContext` and
routes to `RecoveryRequired` — it never proceeds on an assumption. Any ambiguity
about isolation is treated as a failure, not a success. The isolation invariant is
covered by an **automated cross-tenant isolation test** (`CrossTenantCleanupTest`,
UIX8C-R228): after a simulated switch, no row/file/pref belonging to tenant A is
readable under tenant B.

### Audit-safe diagnostics (UIX8C-R227)

Cleanup emits a structured diagnostic (counts of partitions cleared, workers stopped,
DBs closed) with **no** tenant secrets, tokens, PII, or raw identifiers — only
scope labels and counts, consistent with the redaction posture of rule 50.

## Cleanup ordering (UIX8C-R236, transactional / compensating)

The switch/reset pipeline runs in a fixed order so a partial failure never strands
data or leaks across tenants:

1. **No-unsynced gate** — refuse if `pendingUnsyncedCount > 0` (logout/switch/reset
   are blocked; see UIX8C-R229..R234). Unsynced transactions are never deleted to
   satisfy a switch.
2. **Stop workers** — cancel/await `OfflineSalesSyncScheduler` / WorkManager jobs for
   the outgoing tenant so no sync writes race the teardown.
3. **Clear credentials** — wipe the cashier token + session from `SecureTokenStore`.
4. **Close DB** — close the outgoing tenant Room database cleanly.
5. **Clear scoped state** — delete tenant/outlet/cashier/transaction-scoped caches,
   files, printer job state, recent-search history, and nav back-stack.
6. **Build + validate new context** — assemble the new `RuntimeContext` from
   server-derived identity and validate isolation (fail-closed if unproven).
7. **Open new DB** — open the new tenant partition only after validation passes.

The pipeline is **transactional/compensating**: if any step fails, the machine does
not present a half-switched state — it lands on `RecoveryRequired` and retries the
governed cleanup rather than opening a mixed-tenant session. Device-scoped state
(installation id, activation) is untouched throughout, so a switch is never a
re-activation.

## Reuse (no second engine)

`RuntimeContext` composes existing identity produced by the canonical device and
auth domains; `LocalDataCleaner` orchestrates the existing Room databases,
`SecureTokenStore`, and `OfflineSalesSyncScheduler`. No pricing, payment, QRIS,
settlement, or sync logic is duplicated — this layer resolves and scopes identity
only, and hands off to the same canonical repositories used everywhere else
(UIX8C-R247).
