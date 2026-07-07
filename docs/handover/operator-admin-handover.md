# Operator & Admin Handover

Sprint 18 — Pilot Closure & Production Handover Foundation.

Day-two operating guide for the pilot operators and the platform admin. No real
credentials appear here — placeholders only.

## Operator daily flow

1. Open the app, log in, confirm device is registered and subscription active.
2. Sync products/categories (automatic; pull-to-refresh if needed).
3. Take orders → cart → checkout.

## Admin onboarding flow

- Platform admin onboards a tenant: `POST /api/v1/admin/tenant-onboarding`
  (creates tenant + default store + owner user + subscription; idempotent).
- Optionally seed demo data; reset is guarded by `confirm_demo_reset`.

## Device registration flow

- First launch registers the device (`POST /api/v1/devices/register`).
- Device limit enforced by backend; revoke via admin or tenant device endpoint.

## Cashier flow

- Add items → cart → choose payment (CASH online, CASH offline, or QRIS).

## Offline cash flow

- CASH sales work offline and are queued; WorkManager syncs when online.
  QRIS is never available offline.

## QRIS payment status flow

- Backend creates the QRIS payment and is polled for status; webhook confirms.

## Receipt / printer flow

- Backend returns an approved receipt payload; Android formats ESC/POS only.

## Closing / report flow

- Daily closing snapshot per tenant/store/business date (locks the day).
- Reports (daily sales, payment summary, inventory movements) are backend-computed.

## Issue reporting flow

- Operators report issues → field issue register → defect register
  (`/api/v1/admin/pilot-defects`) → triage → fix → retest → verify → close.
