# UIX-8C-06 — Receipt & Printer Threat Model

Scope: the premium receipt, transaction history, and printer failure-state
surfaces. These are presentation projections; transaction authority stays in the
backend and the canonical repositories.

## Assets

- Canonical transaction data (sale, payment, items, totals) — integrity.
- Tenant isolation of receipts and history.
- Bluetooth device metadata (name/MAC) — operational, low sensitivity.
- No credentials, tokens, activation codes, or payment secrets belong on these
  surfaces.

## Threats & mitigations

| Threat | Mitigation | Rule |
|--------|-----------|------|
| Stale/previous receipt shown for the current checkout | Identity-guarded ViewModel publish; one-shot `Event`; restoration from Room | R173/R187/R190 |
| Offline draft presented as synced/settled | Durable save → OFFLINE_PENDING; SYNCED only on recorded ack | R175/R176 |
| Money drift (float / 100× decimal misread) | Whole-rupiah `Long`; `fromDouble` bridge; server strings read as integer, never `RupiahMoney.parse` | R179 |
| Duplicate history for one logical transaction | Reconciler dedups by stable `clientReference`; mismatch → CONFLICT | R181/R182 |
| Cross-tenant history/receipt leakage | Per-tenant Room DB; reconciler preserves scope; backend receipt is tenant-scoped (foreign → 404) | R183 |
| Printer failure corrupts a transaction | Coordinator has no sale/payment/sync/inventory reference; print outcome is presentation-only; reprint reuses the immutable receipt | R191/R192/R193 |
| Over-broad Bluetooth permission | `BLUETOOTH_CONNECT` only; no `BLUETOOTH_SCAN`, no location; least privilege | R194/R195/R196 |
| Secret/PII leakage in logs, print payload, or evidence | Receipt/print content is governed business fields only; no credential/token/PII; typed error messages carry no raw exception payload | R199/R201 |
| Hung printer blocks the app / unbounded loop | Bounded 8s timeout; single active job; no retry loop; off main thread | R200 |
| Exposed unsafe internal identifiers | Receipt references use governed invoice/clientReference, not raw internal ids/secrets | R180 |

## Non-goals

- No new network path for history (no server list endpoint added).
- No trust-all TLS / hostname bypass / cleartext (unchanged from rule 55/UIX-7).
- No change to backend financial authority (regression fence only).

## Residual risk

Physical-device validation of receipt/history rendering, large-font, TalkBack, and
real Bluetooth printer behaviour is deferred to final code freeze (R209). The
immutable failed physical run stays verbatim; UIX-7/UIX-8 remain GO deferred.
