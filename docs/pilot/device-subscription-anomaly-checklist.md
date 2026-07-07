# Device / Subscription Anomaly Monitoring Checklist

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> Device limit and subscription are backend-enforced.

Monitors subscription state and device registration/limit enforcement during the
pilot. Maps to the `subscription_device_status` signal.

## Checks

- [ ] **Subscription status** — `/api/v1/subscription/status` reports the pilot
      tenant as active with the expected plan window.
- [ ] **Device registration** — each pilot device is registered and counted once.
- [ ] **Revoked device blocked** — a revoked device is denied access and shows
      the blocked-state UI (no business API access).
- [ ] **Over-limit device rejection** — registering beyond the device limit is
      rejected by the backend with a clear error.
- [ ] **Operator blocked-state UI** — expired subscription / revoked device shows
      a clear blocked screen; no silent failures.

## Evidence required

- Subscription status response (anonymized).
- Device list count vs. limit (anonymized device labels).
- Screenshot of blocked-state UI (placeholder tenant).

## Escalation

- Business API reachable from a revoked/over-limit device → BLOCKER.
- Active subscription incorrectly blocked → CRITICAL.
