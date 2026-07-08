# Lead Management Policy — Sprint 22

Aish POS Lite / POS Android SaaS. Governs how sales leads are captured, owned, and
progressed through the sales pipeline. This is a **readiness foundation**: it adds
internal, admin-governed lead management on top of the Sprint 21 public website lead
interest flow. It does **not** open self-service signup or automate provisioning.

## Lead sources

- **Public website lead interest** (Sprint 21) — a `lead_interest_submissions` row
  may be imported into a `sales_leads` row by a platform admin. Import is
  idempotent (one sales lead per interest submission).
- **Manual creation** — a platform admin may create a sales lead directly.

## Hard rules (Sprint 22)

- A sales lead **never** creates a tenant.
- A sales lead **never** creates a user.
- A sales lead **never** creates a subscription.
- A sales lead **never** registers a device.
- The sales pipeline **never** performs real billing collection.
- The sales pipeline **never** performs subscription payment automation.
- The sales pipeline **never** calls an external CRM API in Sprint 22.
- The sales pipeline **never** sends real WhatsApp / email / Slack messages.
- Activities marked `WHATSAPP_MANUAL` / `EMAIL_MANUAL` are **manual notes only**.
- `ready_for_onboarding` means a **manual onboarding review** is due — not
  automatic provisioning.

## Ownership & governance

- All sales pipeline APIs live under `/api/v1/admin` and require
  `auth:sanctum` + `platform.admin`. Tenant business users cannot access them.
- Every mutation is recorded to the admin audit log (`admin_audit_logs`).
- Free-text (notes, descriptions) is sanitized: credential / token / secret /
  analytics-pixel patterns are redacted before persistence.

## Privacy & data handling (placeholder)

- Leads hold business contact data only (business name, contact name, email,
  phone, business type, size estimates). No payment credentials are stored.
- A future sprint will formalize retention, consent tracking, and a right-to-erase
  flow before any real CRM or outbound messaging integration is considered.
