# UIX-8C-07 — Auth / Device / Session Threat Model

Scope: the premium startup/auth state machine, device activation + status, cashier
session, runtime-context resolution, and the cross-tenant cleanup pipeline. These
surfaces resolve and scope **identity and trust**; transaction authority stays in the
backend `App\Services\*` domains and the canonical repositories. This model
implements the security posture behind **UIX8C-R217..R237** and extends rules
30/50/55/58.

## Assets

- Cashier session token — confidentiality + integrity (grants tenant API access).
- Device installation id + activation record — device trust anchor.
- Tenant isolation of all local data (Room DBs, caches, files, preferences).
- Unsynced offline transactions — financial durability (must never be lost).
- Server-authoritative device status (active/revoked) — the revocation kill-switch.
- No activation codes, credentials, or payment secrets belong in logs, screenshots,
  or evidence artifacts.

## STRIDE-style threats & mitigations

| # | Threat (STRIDE) | Vector | Mitigation | Rule |
|---|-----------------|--------|-----------|------|
| 1 | **Info-disclosure** — token theft at rest | Rooted device / backup extraction / log scrape | `SecureTokenStore` AndroidKeyStore + AES/GCM (authenticated); `allowBackup=false`; token never logged/screenshotted; legacy plaintext migrated then deleted | R218 |
| 2 | **Elevation** — revoked-device bypass | Back-press, deep link, exported component, process restart, or going offline to keep using a revoked device | Server-authoritative `GET /android/device/status`; `DeviceRevoked` is a fail-closed lock; last-known revoked persists offline; only governed re-activation exits; pending queue quarantined not usable | R219/R221/R234 |
| 3 | **Info-disclosure** — cross-tenant data leakage | Account/device switch leaving tenant A rows readable under tenant B | `LocalDataCleaner` scope classification + full ordered cleanup; fail-closed if clearance unproven; automated `CrossTenantCleanupTest` isolation invariant | R226/R228/R235/R237 |
| 4 | **Spoofing / Replay** — activation code replay | Re-submitting a captured activation code on another device | Server single-use activation + rate-limit + audit; wrong-tenant rejected server-side; sha256-hashed token, never echoed | R217 |
| 5 | **Repudiation / Loss** — unsynced-transaction loss on logout | Cashier logs out / switches while offline transactions pending | Blocking logout/switch/reset gate while `pendingUnsyncedCount > 0`; unsynced never deleted; count includes all non-acked incl `OFFLINE_PENDING` | R229/R230/R231 |
| 6 | **Info-disclosure** — secret in logs / screenshots / evidence | Diagnostics, crash reports, cleanup logs, threat evidence | Cleanup diagnostic carries scope labels + counts only; redaction per rule 50; no token/PII/activation code in any log or evidence artifact | R227/R246 |
| 7 | **Spoofing / Elevation** — client-supplied tenant authority | Intent extra / query / header claiming a tenant/outlet | Identity is server-derived only (`auth/me`, `device/*`); client values compared for consistency, never trusted for authorization; mismatch → `ContextMismatch` | R222/R223/R226 |
| 8 | **Elevation** — deep-link / exported-component bypass | Launching an internal screen directly to skip the boot gate | Only the launcher is exported (UIX7-R028); every screen re-derives `BootState`; no screen renders cashier data without a resolved `Ready`/`OfflineReady`; revoked/expired re-locks | R219/R211 |
| 9 | **Tampering** — forged "healthy"/"online" status | Faking reachability to appear synced | Connectivity ≠ reachability; `online` is a hint, proof requires an authed round trip; unknown status never upgraded to healthy | R216/R242 |
| 10 | **DoS / Loss** — hung/unbounded startup | Corrupt partition or unreachable backend hangs the splash | Bounded startup budget (`withTimeout`); breach → `RecoverableFailure` (retry) or `OfflineReady`; never an indefinite spinner | R212 |
| 11 | **Elevation** — device gate satisfies cashier gate (or vice versa) | Treating one credential as both | Two distinct gates: `device/activate` and `auth/login`; passing one never implies the other; both required for `Ready` | R217 |

## Transport security (stated honestly)

- **Pilot / release** builds target `https://aishpos.online/` over **TLS only** — no
  trust-all `TrustManager`, no hostname-verification override, cleartext denied by
  default (UIX7-R045..R048). The new `device/status` endpoint and the activation /
  login / logout calls all ride this TLS channel on pilot/release.
- **Debug / emulator** builds may use `http://10.0.2.2:8000/` with the debug-only
  cleartext exception in `src/debug/res/xml`. This cleartext exception is confined to
  the debug source set and **never** enters the pilot/release merged manifest or
  network-security config (UIX7-R048). Cleartext is a development affordance, not the
  production transport.

## Non-goals

- No new trust-all TLS / hostname bypass / cleartext on pilot or release (unchanged
  from rule 55 / UIX-7).
- No hardware device identifier (IMEI/serial/MAC/`ANDROID_ID`) is read — the
  installation id is app-generated (R218), removing a fingerprinting/permission
  surface entirely.
- No change to backend financial authority; the backend additions are the read-only
  `device/status` endpoint plus regression fences.

## Residual risk

Physical-device validation of the revoked-device lock on real hardware, TalkBack on
the auth/activation/settings surfaces, on-device large-font (100/115/130%) rendering,
and real Keystore-backed storage on OEM devices is deferred to final code freeze
(UIX8C-R249). The immutable failed physical run `run-97fbb64-2af94aa` stays verbatim;
emulator evidence stays labelled emulator; an operator-observed human PASS is never
fabricated. UIX-7 remains `NO-GO — GO DEFERRED`; UIX-8 remains `IMPLEMENTATION
COMPLETE — GO DEFERRED`.
