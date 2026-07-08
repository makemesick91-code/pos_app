# Manual Follow-up Playbook — Sprint 22

All follow-up in Sprint 22 is **manual and human-driven**. The system records what a
salesperson did; it never performs the outreach itself.

## Golden rule

> **No real sending rule.** Sprint 22 never sends a real WhatsApp message, email, or
> Slack alert, and never calls an external CRM. `WHATSAPP_MANUAL` and `EMAIL_MANUAL`
> activities are **notes describing a manual action already performed by a human**.

## Activity types

| Type | Use |
| ---- | --- |
| `NOTE` | Free-form note |
| `CALL` | Log a phone call (manual) |
| `WHATSAPP_MANUAL` | Note that a WhatsApp message was sent manually |
| `EMAIL_MANUAL` | Note that an email was sent manually |
| `DEMO` | Demo scheduled / performed (placeholder) |
| `PROPOSAL` | Proposal shared (placeholder) |
| `FOLLOW_UP` | Scheduled follow-up |
| `STATUS_CHANGE` | System-recorded stage/status change |
| `ASSIGNMENT` | System-recorded assignment change |
| `QUALIFICATION` | System-recorded qualification |
| `RISK_REVIEW` | Risk review note |
| `ONBOARDING_HANDOVER_REVIEW` | Ready-for-onboarding handoff note |

## Call note

1. Complete the call manually.
2. `POST /sales-leads/{lead}/activities` with `activity_type=CALL`, a `summary`, and
   `notes`. Mark `status=DONE` (or `PLANNED` if scheduling ahead).

## WhatsApp / email manual note

1. Send the message manually from your own device / mail client.
2. Record it as `WHATSAPP_MANUAL` / `EMAIL_MANUAL`. Do **not** paste tokens or
   credentials — secret-looking text is redacted automatically.

## Demo / proposal placeholder

- Record `DEMO` / `PROPOSAL` activities with `scheduled_at` where relevant. There is
  no automated calendar or document generation in Sprint 22.

## Follow-up SLA placeholder

- Schedule `FOLLOW_UP` activities with `scheduled_at`. `sales-pipeline:activity-summary`
  reports an `overdue_placeholder` count (planned activities past their scheduled
  time). Enforced SLA automation is deferred to a future sprint.
