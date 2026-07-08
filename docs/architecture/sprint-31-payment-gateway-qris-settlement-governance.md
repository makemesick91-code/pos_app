# Sprint 31 — Payment Gateway / QRIS Settlement Governance Foundation

## Scope

A **provider-neutral** payment gateway settlement foundation for **Sprint 30
tenant billing invoices** (`tenant_billing_*`). It covers the settlement
lifecycle **invoice → payment intent → verified webhook → settlement →
collection**, webhook signature verification, idempotency/replay protection,
audit logging, redaction, and hard governance gates (`PGW-R001..R018`).

The system is made **ready for a real QRIS provider later**, but CI/tests depend
only on a deterministic **mock** provider — no real gateway credentials and no
network calls.

## Non-goals

- No real provider (Midtrans/Xendit/…) is wired in this sprint; they are declared
  in config but disabled, and requesting one throws `GATEWAY_PROVIDER_NOT_WIRED`.
- No change to the **Sprint 5 POS QRIS** surface (`App\Services\Payments`,
  `/webhooks/payments/{provider}`), which settles point-of-sale transactions.
  Sprint 31 settles **SaaS billing invoices** and is kept fully separate.
- No change to the **Sprint 23 `saas_billing_*`** collection surface.
- No tenant/public mutation route (the verified webhook is not a tenant route).
- No Android behavioural change; no VPS deploy.
- No refunds/chargebacks/partial-settlement flows (deferred).

## Provider-neutral architecture

```
PaymentGatewayProviderContract        ← the only thing the services depend on
  └─ MockQrisPaymentGatewayProvider   ← deterministic, no network, no secret
PaymentGatewayProviderManager         ← resolves an ENABLED provider by key
```

A live provider additionally requires the master `live_gateway_enabled` switch
(default **false**), so in CI only the mock is resolvable (`PGW-R001/R002`).

## Lifecycle: invoice → intent → webhook → settlement → collection

1. **Intent** — `PaymentGatewayIntentService::create(invoice, provider, channel)`
   builds a `tenant_billing_payment_intents` row. The amount is **always** the
   invoice outstanding amount (never client input). A paid invoice is refused
   (`PGW-R004`). While an attempt is open, the same intent is returned on retry
   (`PGW-R003`). The provider returns a deterministic `provider_reference`.
2. **Webhook** — the provider calls
   `POST /api/v1/payment-gateway/{provider}/webhook`.
   `PaymentGatewayWebhookService::ingest()` verifies the signature (`PGW-R007`),
   detects replays (`PGW-R008`), normalizes the event, and routes it.
3. **Settlement** — only a **verified `paid`** event settles.
   `PaymentGatewaySettlementService::settle()` calls the **Sprint 30**
   `TenantPaymentCollectionService::record()` (`PGW-R010`) with the provider
   reference as the idempotency key. A failed/expired/cancelled event updates the
   intent but **never** marks the invoice paid (`PGW-R009`).
4. **Collection** — the Sprint 30 layer recomputes the invoice `collection_state`;
   collected revenue is never overstated, and a manual suspension is never lifted
   (`PGW-R013`).

## Idempotency model

- **Intent**: idempotent per `invoice + provider + channel` while an intent is
  open; an explicit `Idempotency-Key` gives exact idempotency.
- **Webhook**: `UNIQUE(provider, provider_event_id)` and `UNIQUE(provider,
  payload_hash)`; a duplicate is returned as the existing event and never
  reprocessed (`PGW-R008`).
- **Settlement**: the Sprint 30 payment `idempotency_key` is derived from the
  invoice + `provider_reference`, so a replayed paid event never double-collects
  (`PGW-R012`). `UNIQUE(provider, provider_reference)` enforces one settlement per
  provider payment.

## Signature verification model

The provider owns signing. `verifyWebhookSignature(payload, headers)` returns a
boolean verdict plus a **truncated fingerprint** — the raw signature/secret is
never stored or logged (`PGW-R011`). The mock uses a deterministic, **non-secret**
HMAC key over the canonical payload (excluding the signature field) so tests and
smoke can sign reproducibly. An unsigned/invalid event is stored `rejected` and
never processed; the webhook responds `401`.

## Redaction model

`PaymentGatewayRedactor` reuses the Sprint 30 `BillingMetadataSanitizer` (drops
`secret`/`token`/`signature`/`payload`/`card`/`KTP`/`NIK`-like keys, truncates
long strings) and adds `signatureHash()` and `payloadHash()`. All intent/event
metadata, command output, and API responses are redacted (`PGW-R016`).

## Route matrix

| Method | Route | Guard | Purpose |
| --- | --- | --- | --- |
| GET | `/api/v1/admin/tenant-billing/gateway/intents` | `platform.admin` | List intents |
| GET | `/api/v1/admin/tenant-billing/gateway/intents/{intent}` | `platform.admin` | Show intent |
| POST | `/api/v1/admin/tenant-billing/gateway/invoices/{invoice}/intents` | `platform.admin` | Create intent |
| GET | `/api/v1/admin/tenant-billing/gateway/events` | `platform.admin` | List events |
| GET | `/api/v1/admin/tenant-billing/gateway/events/{event}` | `platform.admin` | Show event |
| GET | `/api/v1/admin/tenant-billing/gateway/provider-summary` | `platform.admin` | Provider posture |
| GET | `/api/v1/admin/tenant-billing/gateway/settlement-summary` | `platform.admin` | Settlement aggregates |
| GET | `/api/v1/admin/tenant-billing/gateway/governance-summary` | `platform.admin` | Governance audit |
| POST | `/api/v1/payment-gateway/{provider}/webhook` | signature-only, `throttle:60,1` | Provider settlement callback |

The only unauthenticated write is the verified webhook — not a tenant mutation
route (`PGW-R015`).

## Command matrix

| Command | Default | Purpose |
| --- | --- | --- |
| `payment-gateway:provider-summary` | read-only | Providers/channels/status (no secrets) |
| `payment-gateway:intent-create` | **dry-run** (`--execute`) | Create/preview an intent for an invoice |
| `payment-gateway:webhook-simulate` | **dry-run** (`--execute`) | Signed mock event (paid/failed/expired/cancelled/replay/invalid-signature) |
| `payment-gateway:event-summary` | read-only | Event states aggregate |
| `payment-gateway:settlement-summary` | read-only | Settlement outcomes aggregate |
| `payment-gateway:go-no-go` | read-only | Sprint 31 hard gate |

## Data model

- `tenant_billing_payment_intents` — payable intent per invoice/provider/channel;
  unique `idempotency_key`; unique `(provider, provider_reference)`.
- `tenant_billing_gateway_events` — verified/idempotent event ingestion; unique
  `(provider, provider_event_id)` and `(provider, payload_hash)`.

Settlement itself reuses the Sprint 30 `tenant_billing_payments` table via the
collection service — no third settlement table is introduced (deliberately not
over-modelled).

## Dependency graph

```
Webhook route ─► PaymentGatewayWebhookController
                   └─► PaymentGatewayWebhookService
                         ├─ PaymentGatewayProviderManager ─► MockQrisPaymentGatewayProvider
                         ├─ PaymentGatewayRedactor ─► BillingMetadataSanitizer (Sprint 30)
                         └─ PaymentGatewaySettlementService
                               └─► TenantPaymentCollectionService (Sprint 30)
                                     └─► TenantInvoiceStatusService (Sprint 30)
Admin intent route ─► AdminPaymentGatewayIntentController
                        └─► PaymentGatewayIntentService ─► ProviderManager + Redactor + BillingAuditService
```

## Rollback

Additive only. To roll back: revert the branch merge, then
`php artisan migrate:rollback` drops `tenant_billing_gateway_events` and
`tenant_billing_payment_intents`. No Sprint 24–30 table/behaviour is modified, so
rollback cannot corrupt existing billing/collection state.

## Test / CI / smoke evidence

- Backend feature tests under `backend/tests/Feature/PaymentGateway*` cover config,
  intent, webhook, settlement, admin routes, commands, go/no-go, regression, and a
  secret-leak scan.
- `scripts/sprint31_smoke.sh` runs the structural + command + gate checks.
- `.github/workflows/sprint31-ci.yml` runs backend tests, the gateway gate, the
  Sprint 24–30 prior gates, the smoke script, and the Android build/unit tests.

## Deferred risks

- Real provider adapters (Midtrans/Xendit) — signature schemes and payload shapes
  are provider-specific and wired in a later sprint behind `live_gateway_enabled`.
- Refunds, chargebacks, and partial settlement are out of scope.
- Reconciliation/settlement-report ingestion from provider batch files is deferred.
