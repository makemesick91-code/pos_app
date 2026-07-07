# Failed Sync Monitoring Checklist

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> Offline cash only; QRIS is online-only and never queued offline.

Monitors the offline cash queue and WorkManager sync retry behavior during the
pilot. Maps to the `offline_cash_queue` and `offline_sync_retry` signals.

## Checks

- [ ] **Offline cash queue count** — queued offline sales count is bounded and
      expected for the observed offline window.
- [ ] **Failed sync check** — no sale stuck permanently in a failed state.
- [ ] **Retry status** — WorkManager retries with backoff; attempts increment.
- [ ] **Conflict status** — server idempotency prevents duplicate sales on retry
      (same client reference → same server sale).
- [ ] **Sync after network returns** — queue drains to zero after connectivity
      is restored; all offline sales reconciled.
- [ ] **No QRIS offline** — confirm no QRIS payment was attempted/queued offline.

## Evidence required

- Queue length before/after network restore (placeholder screenshots).
- Server sale IDs reconciled against client references (anonymized).
- Retry/attempt log excerpt (no secrets, no PII).

## Escalation

- Stuck failed sale that does not reconcile after network returns → CRITICAL.
- Duplicate sale created on retry → BLOCKER (idempotency regression).
