# UIX-8C-05 — Payment / Sync UX Threat Model

- Sprint: UIX-8C-05
- Package: `com.aishtech.poslite`

> **No secrets in this document.** This threat model contains no real secret,
> token, key, password, or credential value, and none may ever be added.

## Purpose

Enumerate the threats introduced or touched by the premium cash payment + offline
sync-recovery UX and the mitigations already in place. Financial and transaction
authority stays canonical (backend `App\Services\*` + app repositories); the new
presentation layer must never widen the attack surface.

## Threats and mitigations

### 1. Tender manipulation — overflow, negative, pasted garbage
A cashier or a malicious paste could try to submit a non-numeric, oversized, or
negative tender to fabricate change or a bogus sale.
- **Mitigation:** `TenderValidator` + `RupiahMoney.parse` (locale/overflow-safe,
  `null` on garbage/overflow — never a fabricated 0). Change is never negative;
  `Insufficient`/`Invalid`/`Empty` can never submit. `QuickTenderCalculator` drops
  candidates exceeding `Long` range (never wraps negative).

### 2. `clientReference` exposure / tampering
The idempotency key correlates a logical checkout across retries.
- **Mitigation:** it is an internal device-minted correlation id, not a secret and
  not user-editable; it is never logged with credentials or rendered as sensitive.
  The backend dedupes by `(tenant, store, client_reference)`.

### 3. Cross-tenant retry / replay
A retried or replayed sync could attempt to write into another tenant's ledger.
- **Mitigation:** backend dedupe and sale creation are **tenant-scoped**; retry
  reuses the same tenant-bound transaction and `clientReference`. The app never
  trusts a client-supplied tenant/outlet id for authorization.

### 4. Stale authenticated context
An old session/device identity could be reused after logout/switch.
- **Mitigation:** context comes from authenticated canonical state; account/device
  switch re-scopes local data. Manual retry operates only on the current identity's
  durable rows.

### 5. Unauthorized manual retry
Manual retry must not become an uncontrolled re-submit.
- **Mitigation:** `requestManualRetry()` is guarded and delegates to the canonical
  `syncPending`; `SyncRecoveryPresenter.canManualRetry` allows it only for `FAILED
  && attempts < cap`, never for CONFLICT, poison-at-cap, or PENDING/SYNCING/SYNCED.

### 6. Secret / PII / token / raw-payload leakage in errors & logs
Error strings, offline-queued state, or logs could leak sensitive data.
- **Mitigation:** no payment secrets, tokens, or raw payloads in error messages,
  logs, or evidence; the OkHttp logger redacts `Authorization` and runs debug-only.
  Release/pilot builds deny cleartext and use no trust-all TrustManager.

### 7. TLS / transport confusion
A TLS failure must never be laundered into an offline "success".
- **Mitigation:** `TransportFailureClassifier` is fail-closed; TLS/hostname/trust
  validation failure is a **security** error, classified never-offline. Only genuine
  transport/unavailability failures are offline-eligible.

### 8. Local transaction deletion / loss
A durable queued transaction must not be silently discarded.
- **Mitigation:** durable commit precedes cart-clear; logout/account/device switch
  must not silently drop unsynced transactions; poison rows stay FAILED and visible,
  never auto-dropped.

## Rules

UIX8C-R134 (QRIS online-only), R136 (safe tender parse), R149 (TLS/canonical
rejection never offline success), R157–R160 (safe/bounded/governed manual retry),
plus the credential/PII/token/raw-payload non-leakage discipline (rules 55/56/61).
