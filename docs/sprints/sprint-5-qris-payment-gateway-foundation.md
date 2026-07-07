# Sprint 5 — QRIS Payment Gateway Foundation

## Objective

Establish an architecturally-correct QRIS foundation:

```
Android → Backend → Payment Gateway → Backend Webhook → Android Status
```

QRIS is backend-driven, tenant-isolated, webhook-ready, and testable offline via
a fake/sandbox provider. Android holds no gateway credentials and never calls a
payment gateway directly.

## Source of Truth

- `docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md` (canonical)
- Especially sections 12 (API), 13 (QRIS), 14 (Offline & Sync), 16 (Security),
  17 (Performance), 21 (MVP Scope), 22 (Sprint Roadmap), 25 (No-Go), 26 (DoD).

## Previous Sprint Foundation Lock

Sprint 0–4 rules remain intact in `docs/PROJECT_RULES.md`. Sprint 5 adds the QRIS
runtime rule and extends the Foundation Lock Index without removing prior rules.
Sprint 4 CASH checkout behaviour is unchanged and still passes.

## Scope

In scope: `payment_webhook_logs`, payments QRIS display columns, payment gateway
config, QRIS gateway abstraction (contract + data + fake provider + real provider
stubs + manager), QRIS payment service, webhook verification/processing service,
payment status synchronizer, QRIS create/status/webhook APIs, reconciliation
command foundation, Android QRIS DTO/API/repository/screen, tests, smoke, CI,
docs, rules lock.

Out of scope (deliberately not implemented): production live provider activation,
payout/settlement, refunds, real-provider onboarding UI, printer, offline QRIS,
offline sales sync queue, inventory movement runtime, advanced reports, owner
dashboard.

## Graphify Summary

- **Payments table (Sprint 4):** tenant/store/sale owned; `method`, `amount`,
  `status`, `provider`, `provider_reference`, `paid_at`, `expired_at`,
  `raw_response` (hidden). Sprint 5 adds nullable `qr_payload`, `qr_image_url`,
  `payment_url`, `metadata`.
- **Sale lifecycle:** `payment_status ∈ {UNPAID, PENDING, PAID, FAILED, EXPIRED,
  CANCELLED}`. QRIS creation moves a sale to PENDING; a paid webhook to PAID.
- **Tenant context:** `TenantContext` hydrated by `SetTenantContext` middleware
  from the authenticated user; controllers 404 cross-tenant records.
- **QRIS create flow:** `QrisPaymentController` → `QrisPaymentService` →
  `QrisGatewayManager` → gateway `create()` → Payment (QRIS/PENDING) + sale PENDING.
- **Webhook flow:** `PaymentWebhookController` (unauthenticated) →
  `QrisWebhookService` logs first → verify signature → parse → resolve payment by
  `provider + provider_reference` → `PaymentStatusSynchronizer.apply()` (idempotent).
- **Idempotency:** PAID is terminal and never downgraded; identical status is a
  no-op; duplicate PAID never double-settles the sale.
- **Android QRIS consumption:** `QrisRepository` → `PosApiService` QRIS endpoints;
  `QrisPaymentActivity`/`ViewModel` render QR text + status + refresh.
- **Validation gates:** `scripts/sprint5_smoke.sh`, `php artisan test`, Android
  static validation, secret scan, forbidden-files check.
- **GO tag dependency:** all gates green on `main` → `sprint-5-qris-payment-gateway-foundation-go`.

## Backend Implementation

Migrations:

- `..._add_qris_fields_to_payments_table.php` — nullable `qr_payload`,
  `qr_image_url`, `payment_url`, `metadata`; index `(provider, provider_reference)`.
- `..._create_payment_webhook_logs_table.php` — audit log for every webhook.

Models: `Payment` (adds `PROVIDER_FAKE`, QRIS fillables/casts, `scopeQris`,
`webhookLogs`), `PaymentWebhookLog` (statuses received/processed/ignored/failed).

## Payment Gateway Config

`backend/config/payment_gateway.php` — `default_qris_provider` (env `QRIS_PROVIDER`,
default `fake`), `qris_expiry_minutes`, and per-provider blocks for `fake`,
`midtrans`, `xendit`, `duitku`. Credentials come from env only. `.env.example`
carries safe placeholders; real providers are disabled by default. Android never
reads this config.

## QRIS Gateway Abstraction

`app/Services/Payments/`:

- `Contracts/QrisGateway.php` — `name()`, `create()`, `verifyWebhook()`, `parseWebhook()`.
- `Data/QrisCreateRequest.php`, `Data/QrisCreateResponse.php`, `Data/QrisWebhookPayload.php`.
- `Gateways/FakeQrisGateway.php`, `MidtransQrisGateway.php`, `XenditQrisGateway.php`,
  `DuitkuQrisGateway.php`.
- `QrisGatewayManager.php`, `QrisPaymentService.php`, `QrisWebhookService.php`,
  `PaymentStatusSynchronizer.php`, `Exceptions/PaymentGatewayException.php`.

## Fake/Sandbox QRIS Provider

`FakeQrisGateway` is fully offline and deterministic: it mints a
`FAKE-QRIS-…` reference, a text QR payload
`FAKE-QRIS|SALE:{invoice}|AMOUNT:{amount}|REF:{ref}`, sets `expired_at = now +
configured minutes`, verifies webhooks with
`hash_hmac('sha256', rawBody, QRIS_FAKE_WEBHOOK_SECRET)` via the
`X-Fake-Qris-Signature` header, and maps provider statuses to
PAID/PENDING/EXPIRED/FAILED/CANCELLED. Every test uses this provider — no external
network is touched. Real provider stubs throw `PaymentGatewayException` and refuse
webhooks (return false) until onboarding; they are never claimed production-ready.

## QRIS Payment API

`POST /api/v1/sales/{sale}/payments/qris` (auth + tenant.active + tenant.context).
Body: optional `{ "provider": "fake" }`. Rejects paid/cancelled sales (422),
disabled/unknown providers (422), cross-tenant sales (404). Returns 201 with
`data.{id,sale_id,method,provider,status,amount,provider_reference,qr_payload,
qr_image_url,payment_url,expired_at,paid_at,sale_payment_status}`. A still-valid
PENDING QRIS is reused instead of duplicating a QR.

## Payment Status API

`GET /api/v1/payments/{payment}/status` — tenant-scoped (cross-tenant 404); returns
the QRIS payment view including `sale_payment_status`. Never exposes `raw_response`
or provider secrets.

## Webhook Logging

`POST /api/v1/webhooks/payments/{provider}` (unauthenticated). Every payload is
written to `payment_webhook_logs` (raw body, provider, event_type,
provider_reference, signature_valid, processing_status, error_message) BEFORE any
payment mutation. tenant_id/store_id/payment_id are filled once the payload
resolves to a local payment.

## Webhook Signature Validation

Signature verification is delegated to the resolved gateway
(`verifyWebhook(headers, payload, rawBody)`). Invalid signatures are logged with
`signature_valid = false`, `processing_status = failed`, and return **403** — the
payment/sale are never touched.

## Webhook Idempotency

`PaymentStatusSynchronizer.apply()` locks the payment row and:

- treats an identical incoming status as a no-op;
- never downgrades a PAID payment (PAID is terminal);
- settles the sale exactly once (duplicate PAID does not double-update totals);
- returns whether a real change occurred.

A repeated PAID webhook returns 200 (processed) so the provider stops retrying,
while the DB state is unchanged.

## Payment Reconciliation Command

`php artisan payments:reconcile --date=YYYY-MM-DD` scans PENDING QRIS payments for
the date and expires any past their `expired_at` (driving the same idempotent
state machine, so the sale is reconciled too). CASH payments are never touched. No
external gateway call is made. Output:

```
QRIS payments checked: X
Expired locally: Y
Still pending: Z
```

## Tenant Isolation Rules

- Create/read QRIS require tenant context; cross-tenant sales/payments 404.
- Webhooks resolve a payment purely by `provider + provider_reference`; the
  resulting log is attributed to the resolved payment's tenant, so one tenant can
  never settle or observe another tenant's payment.

## Android Implementation

- `data/remote/dto/QrisPaymentDtos.kt` — `CreateQrisPaymentRequestDto`,
  `QrisPaymentResponse`, `QrisPaymentDto`.
- `core/network/PosApiService.kt` — `createQrisPayment`, `getPaymentStatus`.
- `data/repository/QrisRepository.kt` — `createQrisPayment` / `getPaymentStatus`
  returning `ResultState`. No gateway credential, no direct gateway call.
- `core/ServiceLocator.kt` — `qrisRepository(context)` wiring.

## Android QRIS Screen Foundation

`feature/qris/QrisPaymentActivity.kt` + `QrisPaymentViewModel.kt` +
`res/layout/activity_qris_payment.xml`: title "Pembayaran QRIS", sale id, amount,
QR payload as selectable monospace text (no heavy QR-image dependency),
status, expiry, and a "Perbarui Status" refresh button. Registered in the manifest
(`exported=false`). Launched with a `saleId` extra (developer/demo path); cash
checkout from Sprint 4 is untouched.

## Android Build/Tooling Evidence

Local environment has **JDK 25 only**, no Gradle binary, and no committed wrapper;
AGP 8.7.3 requires JDK 17–21. Android build/unit tests were therefore **not run
locally** — static validation only (structure, package, minSdk/targetSdk, secret
scan, source presence). The Sprint 5 CI job runs the same static validation and
will build + unit test automatically once a wrapper + compatible JDK are present.
This limitation is reported honestly; no build/test pass is claimed.

## Application Rules Update

- `docs/PROJECT_RULES.md`: Foundation Lock Index now lists the Sprint 5 doc; a new
  "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" (17 mandates) added; no
  prior rule removed.
- `backend/config/pos_foundation.php`: `sprint_5` metadata + rules
  `qris_backend_driven_required`, `qris_webhook_ready_required`,
  `payment_gateway_credentials_backend_only`.

## Testing Evidence

Backend `php artisan test`: **110 passed (357 assertions)**. New suites:

- `QrisPaymentApiTest` — create for own unpaid sale, PENDING status, sale→PENDING,
  qr_payload/reference present, reject paid/cancelled, reject disabled provider,
  reuse active pending QRIS.
- `QrisWebhookTest` — valid paid → payment+sale PAID, duplicate idempotent, invalid
  signature logged but no update (403), expired → EXPIRED, failed → FAILED, unknown
  reference logged+ignored, PAID not downgraded.
- `PaymentStatusApiTest` — own status incl. sale_payment_status; cross-tenant 404.
- `PaymentReconciliationCommandTest` — command registered, expired→EXPIRED,
  non-expired stays PENDING, CASH untouched.
- `QrisTenantIsolationTest` — A cannot create for B (404), A cannot read B status
  (404), webhook settles only matching tenant, log attributed to correct tenant.
- `FakeQrisGatewayTest` (unit) — reference/payload/expiry, HMAC verify, status map.

Android unit test source added: `QrisRepositoryTest.kt` (not executed locally — see
tooling evidence).

## Backend Compatibility Evidence

`composer validate --strict` passes. Existing routes intact: health, auth
login/me/logout, tenant-context, sync products/categories, sales index/show/store/
cancel, cash payment. Health endpoint sprint label bumped to "Sprint 5" (test
updated). Cash checkout tests still pass.

## Validation Commands

```bash
bash scripts/sprint5_smoke.sh
cd backend
composer validate --strict
php artisan route:list | grep -E "payments/qris|payments/.*status|webhooks/payments"
php artisan list | grep "payments:reconcile"
php artisan test
```

## Validation Results

- Foundation/rules grep: pass
- Sprint 5 smoke: pass (64/64 checks)
- Backend composer validate: pass
- Backend route compatibility: pass (QRIS + sales + auth + sync present)
- Reconcile command availability: pass
- Backend tests: pass (110/110)
- QRIS/webhook/idempotency tests: pass
- Payment tenant isolation tests: pass
- Android static validation: pass
- Android secret scan: pass (no gateway key in Android source)
- Android build/unit tests: skipped (JDK 25 only, no gradle/wrapper) — documented
- Forbidden files: pass
- Working tree clean at tag time: confirmed on merge

## GO Criteria

1. Foundation remains source of truth. ✅
2. Sprint 0–5 rules present in `docs/PROJECT_RULES.md`. ✅
3. `payment_webhook_logs` table available. ✅
4. Payment gateway config available and env-safe. ✅
5. QRIS gateway contract available. ✅
6. Fake/sandbox provider available. ✅
7. QRIS create endpoint available. ✅
8. Payment status endpoint available. ✅
9. Webhook endpoint available. ✅
10. Webhook payload logging available. ✅
11. Webhook signature validation foundation available. ✅
12. Webhook idempotency tests pass. ✅
13. QRIS updates sale payment_status correctly. ✅
14. Reconciliation command available. ✅
15. Tenant A cannot create/view tenant B QRIS payment. ✅
16. Android QRIS DTO/API/repository available. ✅
17. Android QRIS screen foundation available. ✅
18. Android stores no gateway credential. ✅
19. Android does not call gateway directly. ✅
20. Cash payment Sprint 4 still passes. ✅
21. No payout/refund/printer/offline QRIS/offline sync/inventory runtime. ✅
22. Sprint 5 smoke passes. ✅
23. Backend tests pass. ✅
24. Android static validation passes. ✅
25. Android build/test skipped with valid documented reason. ✅
26. Forbidden files not committed. ✅
27. PR/merge completed. (on merge)
28. GO tag exact-match to main HEAD. (on tag)

## No-Go Checks

None triggered: foundation/rules readable; Sprint 0–4 rules intact; Sprint 5 rule
present; no gateway key in Android source; Android never calls a gateway; no real
credentials committed; tests never require external gateway network; fake provider
present; webhook log present; signature validation present; idempotency tested;
tenant isolation enforced; cash flow intact; QRIS refused for paid sale; backend
tests pass; smoke passes; Android package/minSdk/targetSdk unchanged; no forbidden
files.

## Follow-up for Sprint 6

- Sprint 6 — Printer & Receipt Foundation.
- Later: live provider activation (Midtrans/Xendit/Duitku) after merchant
  onboarding + real credentials; refund/payout; offline QRIS handling.
