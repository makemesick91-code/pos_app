# UIX-8C-04 — Offline CASH Durability Threat Model

Threat review for the durable offline CASH path and its idempotent recovery.
Extends the UIX-7/UIX-8 security posture (rules 55/56/57/58); introduces no
network-security regression.

## Assets

- Financial transaction integrity (exactly one sale/payment/inventory per logical
  checkout).
- Tenant/outlet/device isolation of local and backend data.
- Credentials, Sanctum tokens, activation tokens, customer PII, payment secrets.

## Threats & mitigations

| # | Threat | Mitigation | Rule |
|---|--------|------------|------|
| 1 | Forged / guessed `clientReference` to reconcile another sale | Backend lookup is scoped by `(tenant, store, client_reference)`; a foreign reference cannot resolve another tenant's sale; reference is device-minted UUID-quality, carries no tenant/user secret | R097/R118 |
| 2 | Cross-tenant replay (tenant A's ref used under tenant B) | Unique index `(tenant_id, store_id, client_reference)`; FormRequest scopes store/product to caller tenant; test `test_cross_tenant_reference_reuse_is_isolated` | R118 |
| 3 | `clientReference` collision within a tenant | UUID v4 space; local unique index + `findByClientReference` reconcile; duplicate submit returns the original sale | R109/R118 |
| 4 | Canonical rejection laundered into an offline "success" | Typed classifier: only *thrown* transport failures are eligible; any received HTTP status ⇒ Rejected; 4xx/409/TLS/unknown never queue | R099..R103 |
| 5 | TLS certificate/hostname/trust failure misclassified as offline | SSL/cert exceptions matched **before** generic IOException and marked Ineligible; treated as a security error, not an offline condition; no trust-all / no hostname-verifier override introduced | R103 |
| 6 | Malicious / malformed server response drives false success | Success requires `isSuccessful && body != null`; a malformed body maps to Rejected (reachable) or is caught and classified Ineligible; SYNCED only on a real ACK | R103/R111 |
| 7 | Local DB partial write leaves an orphaned header/item | Single `@Transaction` insert; rollback on failure leaves the cart intact; no partial header-without-items | R106/R108 |
| 8 | Transaction deleted / lost before sync | Durable commit before cart clear; orphan SYNCING rows recovered; bounded retry keeps FAILED rows visible (never silently dropped) | R105/R112/R115/R117 |
| 9 | Payment overstatement / double financial mutation | Whole-Rupiah integer money; backend dedupe skips payment/items/inventory on replay; idempotency tests assert counts | R120..R122 |
| 10 | Cached tenant identity after account/device switch | Tenant-scoped per-tenant Room DB (UIX-7 baseline); account/device switch re-scopes local data; unsynced transactions are not silently discarded | R126 |
| 11 | Secret / PII leakage via logs or the classifier reason | Classifier reasons are static Indonesian strings carrying no request/response payload; OkHttp `Authorization` redaction + debug-only logging (UIX-7 baseline); no token/PII in offline row error field | R127 |
| 12 | Rapid-tap duplicate transaction | ViewModel re-entry guard + stable reference + local/backend idempotency | R109/R116 |

## Residual risks

- **Physical-hardware behaviours** (OEM background restrictions on WorkManager,
  real DNS/airplane transitions) are not exercised by JVM tests; they are
  deferred to the post-freeze physical R11 campaign (UIX8C-R129). This is stated
  honestly, not fabricated.
- **On-device DB tampering** (a rooted device editing `offline_sales`) is out of
  scope; the app trusts its own tenant-scoped local store as the UIX-7 baseline
  does. Backend dedupe remains the financial authority regardless.

## Non-goals / invariants held

No cleartext traffic, no trust-all TLS, no disabled hostname verification, no new
exported component, no QRIS-offline path. Network-security config is unchanged.
