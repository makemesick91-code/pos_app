# Manual Collection Playbook (Sprint 23)

Billing collection in Sprint 23 is **manual, human-driven governance**. Every
collection touch is recorded as a `SaasBillingCollectionActivity`. No activity ever
sends a real message.

## Activity types

- `NOTE` — a free-text collection note.
- `CALL` — a record of a phone call made manually.
- `WHATSAPP_MANUAL` — a note that a WhatsApp message was sent manually, out-of-band.
  **The system never sends WhatsApp.**
- `EMAIL_MANUAL` — a note that an email was sent manually, out-of-band. **The system
  never sends email.**
- `INVOICE_ISSUED` — a note that an invoice was issued.
- `PAYMENT_FOLLOW_UP` — a scheduled/completed follow-up on an outstanding invoice.
- `PAYMENT_REVIEW` — a note about reviewing submitted payment evidence.
- `DISPUTE_REVIEW` — a note about reviewing a disputed invoice.
- `OVERDUE_REVIEW` — a note about reviewing an overdue invoice.
- `COLLECTION_ESCALATION` — a placeholder escalation record for future
  (non-Sprint-23) escalation workflows. It is a note only.

## Statuses

`PLANNED → DONE` / `CANCELLED` / `SKIPPED`.

## Playbook

1. Issue the invoice (`INVOICE_ISSUED`).
2. On or before the due date, plan a `PAYMENT_FOLLOW_UP` (schedule it).
3. Contact the tenant manually (record a `CALL` / `WHATSAPP_MANUAL` / `EMAIL_MANUAL`
   note — the message itself is sent by a human outside the system).
4. When a manual payment proof arrives, record payment evidence and a
   `PAYMENT_REVIEW` note.
5. If overdue, record an `OVERDUE_REVIEW` and consider raising a billing collection
   risk (see [overdue-dispute-governance.md](overdue-dispute-governance.md)).
6. If disputed, record a `DISPUTE_REVIEW` and mark the invoice disputed.
7. Escalation is a manual `COLLECTION_ESCALATION` note only — no automated action.

## Rule

`WHATSAPP_MANUAL` / `EMAIL_MANUAL` / `COLLECTION_ESCALATION` are **notes only**. The
platform never sends a real WhatsApp/email/Slack/Telegram message in Sprint 23.
