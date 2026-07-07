# Operator Feedback Log

> Sprint 16 — Pilot Monitoring & Hypercare Foundation.
> **Safety:** no passwords, no secrets, no real private customer data. Use
> placeholders or anonymized values only (e.g. `operator@example.test`,
> `DEMO_TENANT_PLACEHOLDER`, `DEVICE-XX`).

Captures structured operator feedback during the pilot hypercare window. Each
entry may link to a hypercare issue in `docs/pilot/field-issue-register.md`.

## Log

| Date | Operator | Device | Tenant/Store | Area | Feedback | Impact | Linked Issue | Status | Follow-up |
|------|----------|--------|--------------|------|----------|--------|--------------|--------|-----------|
| `YYYY-MM-DD` | operator@example.test | DEVICE-01 | DEMO_TENANT_PLACEHOLDER | cashier_sales | (example) checkout felt slow at peak | MINOR | — | OPEN | Watch peak latency |
| `YYYY-MM-DD` | operator@example.test | DEVICE-02 | DEMO_TENANT_PLACEHOLDER | payment_qris | (example) QRIS status took a few seconds | MINOR | — | OPEN | Monitor QRIS polling |

## Fields

- **Operator** — anonymized email/handle placeholder.
- **Device** — device label placeholder, not a real IMEI/serial.
- **Tenant/Store** — demo tenant placeholder only.
- **Area** — one of the canonical health areas.
- **Impact** — mapped to a severity in `field-issue-severity-sla.md`.
- **Linked Issue** — hypercare issue ID if escalated.

## Rules

- Anonymize all personal and customer data.
- Do not paste tokens, API keys, `.env` values, or receipts with real PII.
- Escalate MAJOR+ feedback into the hypercare issue register.
