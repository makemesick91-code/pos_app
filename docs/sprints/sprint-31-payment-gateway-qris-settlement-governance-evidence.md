# Sprint 31 — Payment Gateway / QRIS Settlement Governance Foundation (Evidence)

Baseline: Sprint 30 merged (`023476f`), GO tag
`sprint-30-billing-invoice-generation-payment-collection-governance-foundation-go`.
Branch: `feature/sprint-31-payment-gateway-qris-settlement-governance-foundation`.

## Objective

A provider-neutral payment gateway settlement foundation for Sprint 30 tenant
billing invoices — QRIS/settlement lifecycle, webhook ingestion, idempotency,
audit logging, redaction, and governance gates — ready for a real provider later
without any real credential or network call in CI.

## Runtime shipped

- **Config**: `backend/config/payment_gateway_governance.php` (providers, channels,
  policies, `PGW-R001..R018`, guardrails, doc/command contracts).
- **Tables**: `tenant_billing_payment_intents`, `tenant_billing_gateway_events`.
- **Models**: `TenantBillingPaymentIntent`, `TenantBillingGatewayEvent`.
- **Services** (`App\Services\PaymentGateway`): `PaymentGatewayProviderManager`,
  `Contracts\PaymentGatewayProviderContract`, `Providers\MockQrisPaymentGatewayProvider`,
  `PaymentGatewayIntentService`, `PaymentGatewayWebhookService`,
  `PaymentGatewaySettlementService`, `PaymentGatewayRedactor`,
  `PaymentGatewaySummaryService`, `PaymentGatewayGovernanceAuditService`,
  `PaymentGatewayGoNoGoService`, `PaymentGatewayException`, and `Data\*`
  (`PaymentIntentResult`, `WebhookVerification`, `NormalizedWebhookEvent`).
- **Admin controllers**: `AdminPaymentGatewayIntentController`,
  `AdminPaymentGatewayEventController`, `AdminPaymentGatewayGovernanceController`.
- **Webhook controller**: `PaymentGatewayWebhookController`.
- **Commands**: `payment-gateway:provider-summary`, `:intent-create`,
  `:webhook-simulate`, `:event-summary`, `:settlement-summary`, `:go-no-go`.

## Key confirmations

- Intent amount is always the invoice outstanding amount; a paid invoice refuses a
  new intent (`PGW-R004`); intent creation is idempotent (`PGW-R003`).
- An unsigned/invalid webhook is `rejected` and never processed (`PGW-R007`); the
  route responds `401`.
- Webhook replay is idempotent — a duplicate `provider_event_id`/payload returns
  the existing event and never reprocesses/double-collects (`PGW-R008/R012`).
- Only a verified `paid` event settles, through the Sprint 30 collection service
  (`PGW-R010`); failed/expired/cancelled events never mark an invoice paid
  (`PGW-R009`).
- Settlement never lifts a manual tenant suspension (`PGW-R013`).
- All admin mutations require `platform.admin` (`PGW-R014`); no tenant/public
  gateway mutation route exists (`PGW-R015`).
- No secret/PII in config, audit, command, smoke, or API output (`PGW-R011/R016`).

## Regression confirmations (Sprint 24–30)

- Sprint 5 POS QRIS surface (`/webhooks/payments/*`, `App\Services\Payments`) is
  untouched and does not collide with the Sprint 31 `/payment-gateway/*` webhook.
- Sprint 30 `tenant-billing/*` routes and services are unchanged; Sprint 23
  `saas_billing_*` and `billing/*` are untouched.
- Prior gates remain registered and green:
  `billing:go-no-go`, `subscription-renewal:go-no-go`,
  `tenant-lifecycle:go-no-go`, `tenant-plan:go-no-go`,
  `report-export-metering:go-no-go`, `usage-ledger:go-no-go`,
  `export-governance:go-no-go`.

## Rollback

Revert the branch merge and `php artisan migrate:rollback` (drops the two Sprint
31 tables). No prior-sprint behaviour changes, so rollback is safe.

## Deferred items / risks

- Real provider adapters (Midtrans/Xendit) behind `live_gateway_enabled`.
- Refunds/chargebacks/partial settlement.
- Provider settlement-report/batch reconciliation.

## Evidence commands

```
php artisan payment-gateway:provider-summary
php artisan payment-gateway:go-no-go --strict --json
bash scripts/sprint31_smoke.sh
php artisan test --filter PaymentGateway
```
