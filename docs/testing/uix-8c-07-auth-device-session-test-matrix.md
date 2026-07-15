# UIX-8C-07 — Auth / Device / Session Test Matrix

Automated coverage (pure-JVM JUnit4 + coroutines-test + arch core-testing for
Android; PHPUnit for backend) plus the backend regression fence. Physical / on-device
large-font (100/115/130%), TalkBack, and revoked-device runtime validation is
operator-performed and deferred to final code freeze (**UIX8C-R249**). Emulator and
fake-adapter evidence is never relabelled physical.

Columns: **Scenario | Rule | Test type | Test class | Evidence source**.
Evidence source is one of `emulator`, `automated_test`, `database`. Rows marked
**⟳ operator-observed** additionally require an explicit human PASS (font-130% and
TalkBack) and are **never fabricated** (UIX8C-R248/R250).

## Startup / boot state machine (UIX8C-R211..R216)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Cold start, no activation → `ActivationRequired` | R211/R213 | android-unit | `StartupCoordinatorTest` | automated_test |
| Valid device, no session → `LoginRequired` (after restore) | R211/R214 | android-unit | `StartupCoordinatorTest` | automated_test |
| All durable preconditions + reachable → `Ready` | R211/R213 | android-unit | `StartupCoordinatorTest` | automated_test |
| All durable valid, unreachable → `OfflineReady` (not Ready) | R216 | android-unit | `StartupCoordinatorTest` | automated_test |
| Returning valid session → no `LoginRequired` flash | R214 | android-unit | `StartupCoordinatorTest` | automated_test |
| Connectivity true but backend unreachable → offline-authoritative | R216 | android-unit | `StartupCoordinatorTest` | automated_test |
| Restore inconsistent-recoverable → `RecoveryRequired` (fail-closed) | R215/R226 | android-unit | `StartupCoordinatorTest` | automated_test |
| Transient timeout → `RecoverableFailure`, bounded (no hang) | R212 | android-unit | `StartupCoordinatorTest` | automated_test |
| Boot progress states render distinct (splash → migration → restore) | R242/R244 | android-ui | `AuthDeviceLayoutTest` | emulator |

## Device activation (UIX8C-R217/R218/R221)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Activation success → device gate valid, installation id bound | R217/R218 | android-unit | `DeviceActivationViewModelTest` | automated_test |
| Activation code expired → `DeviceInvalid`, typed error | R217 | android-unit | `DeviceActivationViewModelTest` | automated_test |
| Activation code invalid/malformed → `DeviceInvalid` | R217 | android-unit | `DeviceActivationViewModelTest` | automated_test |
| Activation code already used → rejected, no local trust | R217 | backend | `DeviceActivationProvisioningTest` | database |
| Activation wrong-tenant → rejected server-side | R217/R223 | backend | `DeviceActivationProvisioningTest` | database |
| Activation offline → transport failure, no fabricated success | R216/R217 | android-unit | `DeviceActivationViewModelTest` | automated_test |
| Installation id is app-generated (never IMEI/serial/MAC) | R218 | android-unit | `RuntimeContextTest` | automated_test |
| Activate/status/revoke device lifecycle server-authoritative | R221 | backend | `Uix8c07DeviceLifecycleTest` | database |

## Cashier login (UIX8C-R217/R223)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Login success → session + cashier identity populated | R217 | android-unit | `DeviceActivationViewModelTest` / `SessionEventsTest` | automated_test |
| Login invalid credentials (401) → `LoginRequired`, typed error | R217 | android-unit | `SessionEventsTest` | automated_test |
| Login locked account → typed error, no bypass | R217/R219 | backend | `Uix8c07DeviceLifecycleTest` | database |
| Login wrong-outlet → rejected, context inconsistent | R223 | android-unit | `RuntimeContextTest` | automated_test |
| Login on session-expired → re-auth reuses device gate | R233 | android-unit | `SessionEventsTest` | automated_test |
| Login offline → no fabricated online success | R216 | android-unit | `SessionEventsTest` | automated_test |

## Session expiry & revoked device (UIX8C-R219/R233)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| 401 on authed call → `SessionExpired`, unsynced preserved | R233 | android-unit | `SessionEventsTest` | automated_test |
| Session-expired → re-auth restores `Ready`, no data loss | R233 | android-unit | `SessionEventsTest` | automated_test |
| `device/status` → revoked maps to `DeviceRevoked` | R221 | android-unit | `DeviceStatusMapperTest` | automated_test |
| Revoked no-bypass via back-press | R219 | android-ui | `AuthDeviceLayoutTest` | emulator |
| Revoked no-bypass via deep-link / exported component | R219 | android-ui | `AuthDeviceLayoutTest` | emulator |
| Revoked no-bypass via process restart | R219 | android-unit | `StartupCoordinatorTest` | automated_test |
| Revoked no-bypass while offline (last-known revoked stays) | R219 | android-unit | `DeviceStatusMapperTest` | automated_test |
| Revoked → pending queue quarantined (not deleted) | R234 | android-unit | `LogoutGuardTest` | automated_test |
| Server revoke reflected by status endpoint | R221 | backend | `DeviceStatusEndpointTest` | database |

## Tenant / outlet mismatch (UIX8C-R222/R223/R226)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Restored tenant/outlet inconsistent → `ContextMismatch` | R222/R226 | android-unit | `StartupCoordinatorTest` | automated_test |
| Client-supplied tenant/outlet never trusted for authority | R223 | android-unit | `RuntimeContextTest` | automated_test |
| Server-derived identity is the only authority | R223 | backend | `DeviceStatusEndpointTest` | database |

## Runtime context & secure storage (UIX8C-R222/R225/R218)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| `RuntimeContext` immutable; single source of scope | R222 | android-unit | `RuntimeContextTest` | automated_test |
| Restored context validated, not raw-trusted | R225 | android-unit | `RuntimeContextTest` | automated_test |
| Secure store round-trips token via AndroidKeyStore AES/GCM | R218 | android-unit | `SecureTokenStoreTest` | automated_test |
| Legacy plain-prefs token migrated + plaintext removed | R218 | android-unit | `SecureTokenStoreTest` | automated_test |
| Token never present in logs | R218 | android-unit | `SecureTokenStoreTest` | automated_test |

## Unsynced-transaction safety & logout gate (UIX8C-R229..R234)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Logout with 0 pending → allowed | R229/R230 | android-unit | `LogoutGuardTest` | automated_test |
| Logout with 1 pending → blocked, affordance shown | R230/R232 | android-unit | `LogoutGuardTest` | automated_test |
| Logout with many pending → blocked, count reported | R230/R231 | android-unit | `LogoutGuardTest` | automated_test |
| Pending count includes all non-acked (incl. `OFFLINE_PENDING`) | R231 | android-unit | `LogoutGuardTest` | automated_test |
| Unsynced transactions never deleted to satisfy logout | R229 | android-unit | `LogoutGuardTest` | automated_test |
| Session-expired preserves unsynced + re-auth | R233 | android-unit | `SessionEventsTest` | automated_test |
| Revoked device quarantines (not deletes) pending queue | R234 | android-unit | `LogoutGuardTest` | automated_test |

## Account switch / tenant reset & cross-tenant cleanup (UIX8C-R226/R228/R235..R237)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Account switch blocked while pending unsynced | R230/R235 | android-unit | `LogoutGuardTest` | automated_test |
| Tenant reset blocked while pending unsynced | R230/R235 | android-unit | `LogoutGuardTest` | automated_test |
| Switch ≠ re-activation (device-scoped state preserved) | R235 | android-unit | `LocalDataCleanerTest` | automated_test |
| Cleanup follows full ordered pipeline | R236 | android-unit | `LocalDataCleanerTest` | automated_test |
| Cleanup transactional/compensating on partial failure | R236 | android-unit | `LocalDataCleanerTest` | automated_test |
| Scope classification global/device/tenant/outlet/cashier/tx | R237 | android-unit | `LocalDataCleanerTest` | automated_test |
| Cross-tenant isolation: tenant A data unreadable under B | R228 | android-unit | `CrossTenantCleanupTest` | automated_test |
| Isolation fail-closed when clearance unproven | R226 | android-unit | `CrossTenantCleanupTest` | automated_test |
| Cleanup diagnostic carries no secret/PII (counts only) | R227 | android-unit | `LocalDataCleanerTest` | automated_test |

## Process restoration (UIX8C-R238..R241)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Process restore derives truth from Room, no duplicate | R238/R241 | android-unit | `StartupCoordinatorTest` | automated_test |
| Restore only re-validated safe state (no stale identity) | R239 | android-unit | `RuntimeContextTest` | automated_test |
| Restore idempotent via UIX-8C-06 `clientReference` | R240 | android-unit | `StartupCoordinatorTest` | automated_test |
| Restart on revoked device stays locked (no duplicate sale) | R219/R241 | android-unit | `StartupCoordinatorTest` | automated_test |

## Truthful status mapping (UIX8C-R242..R244)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Sync status source-of-truth mapping (pending/syncing/synced/failed) | R242/R243 | android-unit | `DeviceStatusMapperTest` | automated_test |
| Printer status distinct labels mapped truthfully | R243 | android-unit | `SettingsViewModelTest` | automated_test |
| Connection status connectivity ≠ reachability | R216/R242 | android-unit | `DeviceStatusMapperTest` | automated_test |
| Status never colour-alone (text label present) | R244 | android-ui | `AuthDeviceLayoutTest` | emulator |

## Premium settings surface (UIX8C-R245..R247)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Settings sections present + truthful values | R245 | android-unit | `SettingsViewModelTest` | automated_test |
| Absent value renders "Tidak tersedia" (no fabricated 0) | R245 | android-unit | `SettingsViewModelTest` | automated_test |
| No secret/token rendered in any settings row | R246 | android-unit | `SettingsViewModelTest` | automated_test |
| Settings reuse canonical reads (no second engine) | R247 | android-unit | `SettingsViewModelTest` | automated_test |

## Accessibility & font scale (UIX8C-R248)

| Scenario | Rule | Test type | Test class | Evidence source |
|----------|------|-----------|------------|-----------------|
| Activation/login/settings targets ≥48dp | R248 | android-ui | `AuthDeviceLayoutTest` | emulator |
| Focus order follows status → context → action → recovery | R248 | android-ui | `AuthDeviceLayoutTest` | emulator |
| Structural font-scale (weighted/scroll-reachable) | R248 | android-ui | `AuthDeviceLayoutTest` | emulator |
| Font 100/115/130% primary actions visible ⟳ operator-observed | R248 | a11y | on-device (deferred) | emulator (labelled) |
| TalkBack focus + spoken labels ⟳ operator-observed | R248 | a11y | on-device (deferred) | emulator (labelled) |

## Android test classes

- `StartupCoordinatorTest` — pure boot-state decision table (all transitions).
- `LogoutGuardTest` — logout/switch/reset unsynced gate; quarantine on revoke.
- `LocalDataCleanerTest` — scope classification + ordered/compensating cleanup.
- `DeviceStatusMapperTest` — `device/status` → revoked/active; offline last-known.
- `RuntimeContextTest` — single-source scope, server-derived, validated-not-raw.
- `SecureTokenStoreTest` — AndroidKeyStore AES/GCM round-trip + legacy migration.
- `SessionEventsTest` — 401 → SessionExpired; re-auth preserves unsynced.
- `DeviceActivationViewModelTest` — activation success/expired/invalid/offline.
- `SettingsViewModelTest` — section values, "Tidak tersedia", no-secret, reuse.
- `AuthDeviceLayoutTest` — structural font-scale, ≥48dp, focus order, no-colour-alone.
- `CrossTenantCleanupTest` — automated tenant-isolation invariant.

## Backend test classes

- `DeviceStatusEndpointTest` — `GET /android/device/status` active/revoked + reason,
  tenant-scoped, server-authoritative.
- `DeviceActivationProvisioningTest` — activation single-use, wrong-tenant reject,
  already-used reject, audit-safe.
- `Uix8c07DeviceLifecycleTest` — activate → status → revoke lifecycle; locked login.

## Deferred to physical (post code freeze)

On-device large-font (100/115/130%) visual observation, TalkBack focus order + spoken
labels on the auth/activation/settings surfaces, and real revoked-device lock
behaviour on hardware. Emulator / fake-adapter evidence stays labelled emulator; an
operator-observed human PASS is never fabricated (UIX8C-R249/R250). UIX-7 remains
`NO-GO — GO DEFERRED`; UIX-8 remains `IMPLEMENTATION COMPLETE — GO DEFERRED`.
