# Dunning Manual Notice Policy (Sprint 24)

Subscription dunning is a **manual reminder queue**. A dunning notice is an
internal record of an action a human performs outside the system. Sprint 24 never
dispatches a real message and never stores a secret.

## Notice types

- `RENEWAL_REMINDER`
- `PAYMENT_REMINDER`
- `GRACE_NOTICE`
- `OVERDUE_NOTICE`
- `FINAL_MANUAL_REVIEW_NOTICE`

## Notice statuses

`PLANNED` → `PREPARED` → `MARKED_SENT_MANUALLY` → `COMPLETED`, or `CANCELLED` /
`SKIPPED` at any point.

- **PLANNED** — queued.
- **PREPARED** — admin has drafted the manual message preview.
- **MARKED_SENT_MANUALLY** — admin records that they sent the reminder through an
  external channel by hand. **No real message is sent by the system.**
- **COMPLETED** — the reminder cycle for this notice is closed.
- **CANCELLED / SKIPPED** — not sent.

## Manual channel labels

`WHATSAPP_MANUAL`, `EMAIL_MANUAL`, `CALL_MANUAL`, `IN_APP_ADMIN_NOTE`,
`OTHER_MANUAL`. These are **labels describing how the admin acted manually** — the
platform integrates with no WhatsApp/email/SMS provider in Sprint 24.

## Guardrails

- Manual queue only — no real email/WhatsApp/SMS/Slack sending.
- The per-candidate active-notice count is capped by the policy's
  `max_manual_dunning_notices`.
- `manual_message_preview` and notes are sanitized: credentials, tokens, gateway
  keys and analytics/ad pixel tokens are redacted.
- No secret storage.

See [subscription-renewal-policy.md](subscription-renewal-policy.md) and
[grace-overdue-governance.md](grace-overdue-governance.md).
