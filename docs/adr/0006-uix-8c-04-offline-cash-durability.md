# ADR 0006 — UIX-8C-04 Offline CASH Durability & Idempotent Recovery

- Status: Accepted
- Date: 2026-07-15
- Sprint: UIX-8C-04
- Supersedes: none
- Related: rules 55/56/57/58/59/61; ADR 0002/0003/0004/0005

## Context

Physical run `run-97fbb64-2af94aa` finding R11 (FAIL) showed that an offline CASH
checkout on a real device attempted the backend, surfaced the DNS/transport
failure as a hard error, and persisted **no** durable `offline_sales` row
(count 0). The transaction could not survive process death or reconnect. The
root cause (see `docs/architecture/uix-8c-04-offline-cash-root-cause-analysis.md`)
was that the online CASH path had **no governed transport fallback**: durable
offline save was only reachable via a *separate, manually-selected* button, and
the untyped `catch (Exception)` could not distinguish a governed transport
failure from a canonical rejection.

## Decision

1. **Introduce a typed transport classifier** (`TransportFailureClassifier`) as
   the single, allow-listed, fail-closed gate for offline eligibility. Only
   genuine transport/unavailability exceptions are Eligible; TLS/security,
   serialization, programming, and any received HTTP status are Ineligible.

2. **Make the online CASH path degrade gracefully.**
   `SalesRepository.submitCash` returns a typed `CheckoutOutcome`; the ViewModel
   automatically performs a durable offline CASH save on an Eligible transport
   failure and keeps the cart with a truthful error on any Rejected/Failed
   outcome. This is a *state-machine* change, not a payment-UI rebuild.

3. **One stable `clientReference` per logical checkout**, reused across the online
   attempt, the offline fallback, process restart, reconnect, and worker replay;
   `OfflineSaleRepository.createOfflineCashSale` accepts and reuses it and is
   idempotent via `findByClientReference`.

4. **Do not change backend financial behaviour.** The backend already dedupes by
   `(tenant, store, client_reference)`; UIX-8C-04 adds regression tests only.

5. **No Room schema change.** The existing entities and the unique
   `clientReference` index already support idempotency.

## Alternatives considered

- *Auto-detect connectivity and choose offline up front.* Rejected: connectivity
  ≠ reachability (UIX8-R015); the authoritative signal is the actual submit
  outcome, so classify the *attempt result*, not a pre-flight guess.
- *Queue on any `IOException`.* Rejected: it would launder TLS/security failures
  and ambiguous errors into offline "success" (violates R103); the classifier is
  allow-listed instead.
- *Fix the backend.* Rejected: the audit found the backend already idempotent; a
  rewrite would add risk with no benefit.

## Consequences

- The premium payment/receipt/history screens are **not** rebuilt here (deferred
  to UIX-8C-05); only the truthful offline-queued state is added.
- A fresh physical R11 revalidation on the frozen final APK remains mandatory
  (UIX8C-R129); this ADR and sprint do not close UIX-7/UIX-8 runtime GO
  (UIX8C-R130). The historical R11 evidence stays immutable.
- A test-only Gradle dependency (`androidx.arch.core:core-testing`) is added for
  ViewModel LiveData assertions; it ships in no app variant.
