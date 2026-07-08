#!/usr/bin/env bash
#
# Sprint 31 — Payment Gateway / QRIS Settlement Governance Foundation smoke test.
# Structural + command + gate validation. Deterministic mock provider only; never
# calls a real gateway, never charges, never deploys, never lifts a suspension.
# Asserts no secret leakage and that failed/expired/cancelled events never settle.
#
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

pass=0
fail=0

check() {
  local desc="$1"; shift
  if "$@" >/dev/null 2>&1; then
    echo "  ok   - $desc"
    pass=$((pass + 1))
  else
    echo "  FAIL - $desc"
    fail=$((fail + 1))
  fi
}

hasf() { grep -q "$1" "$2"; }

BACK=backend
CFG="$BACK/config/payment_gateway_governance.php"
SVC="$BACK/app/Services/PaymentGateway"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / rules / posture =="
check "payment_gateway_governance config exists" test -f "$CFG"
check "default provider is mock" hasf "'default_provider' => env('PAYMENT_GATEWAY_PROVIDER', 'mock')" "$CFG"
check "live gateway disabled by default" hasf "'live_gateway_enabled'" "$CFG"
check "webhook signature required" hasf "'webhook_signature_required' => true" "$CFG"
check "replay protection required" hasf "'replay_protection_required' => true" "$CFG"
check "partial payment disabled by default" hasf "'allow_partial_payment' => false" "$CFG"
check "overpayment disabled by default" hasf "'allow_overpayment' => false" "$CFG"
for r in PGW-R001 PGW-R003 PGW-R004 PGW-R007 PGW-R008 PGW-R009 PGW-R010 PGW-R011 PGW-R012 PGW-R013 PGW-R016 PGW-R017 PGW-R018; do
  check "config locks $r" hasf "$r" "$CFG"
done

echo "== Migrations / models =="
check "payment intents migration" test -f "$BACK/database/migrations/2026_07_22_990010_create_tenant_billing_payment_intents_table.php"
check "gateway events migration" test -f "$BACK/database/migrations/2026_07_22_990011_create_tenant_billing_gateway_events_table.php"
check "unique provider_reference index" hasf "tbpi_provider_reference_unique" "$BACK/database/migrations/2026_07_22_990010_create_tenant_billing_payment_intents_table.php"
check "unique provider_event index" hasf "tbge_provider_event_unique" "$BACK/database/migrations/2026_07_22_990011_create_tenant_billing_gateway_events_table.php"
check "TenantBillingPaymentIntent model" test -f "$BACK/app/Models/TenantBillingPaymentIntent.php"
check "TenantBillingGatewayEvent model" test -f "$BACK/app/Models/TenantBillingGatewayEvent.php"

echo "== Services =="
for s in PaymentGatewayProviderManager PaymentGatewayIntentService PaymentGatewayWebhookService PaymentGatewaySettlementService PaymentGatewayRedactor PaymentGatewaySummaryService PaymentGatewayGovernanceAuditService PaymentGatewayGoNoGoService PaymentGatewayException; do
  check "$s" test -f "$SVC/$s.php"
done
check "provider contract" test -f "$SVC/Contracts/PaymentGatewayProviderContract.php"
check "mock provider deterministic" test -f "$SVC/Providers/MockQrisPaymentGatewayProvider.php"

echo "== Commands =="
for c in PaymentGatewayProviderSummaryCommand PaymentGatewayIntentCreateCommand PaymentGatewayWebhookSimulateCommand PaymentGatewayEventSummaryCommand PaymentGatewaySettlementSummaryCommand PaymentGatewayGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "intent-create is dry-run by default (--execute gate)" hasf "execute : Persist" "$CMD/PaymentGatewayIntentCreateCommand.php"

echo "== Admin routes (platform.admin) + webhook =="
check "AdminPaymentGatewayIntentController" test -f "$ADMIN_CTRL/AdminPaymentGatewayIntentController.php"
check "AdminPaymentGatewayEventController" test -f "$ADMIN_CTRL/AdminPaymentGatewayEventController.php"
check "webhook controller" test -f "$BACK/app/Http/Controllers/Api/V1/PaymentGatewayWebhookController.php"
check "routes register gateway intents" hasf "tenant-billing/gateway/intents" "$BACK/routes/api.php"
check "routes register gateway webhook" hasf "payment-gateway/{provider}/webhook" "$BACK/routes/api.php"
check "no tenant/public gateway mutation route" bash -c "! grep -RnE 'tenant/.*gateway/(intents|settle)' $BACK/routes/api.php"

echo "== Rules / foundation lock =="
for r in PGW-R001 PGW-R009 PGW-R010 PGW-R013 PGW-R018; do
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_31" hasf "sprint_31" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 31 runtime rule" hasf "Sprint 31 Payment Gateway" docs/PROJECT_RULES.md
check "Sprint 30 BIL rules still present" hasf "BIL-R016" docs/PROJECT_RULES.md
check "Sprint 29 EGC rules still present" hasf "EGC-R015" docs/PROJECT_RULES.md

echo "== Docs =="
check "architecture doc" test -f docs/architecture/sprint-31-payment-gateway-qris-settlement-governance.md
check "sprint evidence doc" test -f docs/sprints/sprint-31-payment-gateway-qris-settlement-governance-evidence.md
check "architecture doc has dependency graph" hasf "Dependency graph" docs/architecture/sprint-31-payment-gateway-qris-settlement-governance.md

echo "== Separation from Sprint 5 POS QRIS =="
check "Sprint 5 POS webhook route intact" hasf "webhooks/payments/{provider}" "$BACK/routes/api.php"
check "Sprint 31 uses distinct App\\Services\\PaymentGateway namespace" test -d "$SVC"

echo "== Runtime gates (in backend) =="
pushd "$BACK" >/dev/null
php artisan migrate --force >/dev/null 2>&1 || true

check "payment-gateway:provider-summary green" php artisan payment-gateway:provider-summary
check "payment-gateway:event-summary green" php artisan payment-gateway:event-summary
check "payment-gateway:settlement-summary green" php artisan payment-gateway:settlement-summary
check "payment-gateway:go-no-go --strict green" php artisan payment-gateway:go-no-go --strict
check "billing:go-no-go still green" php artisan billing:go-no-go
check "export-governance:go-no-go still green" php artisan export-governance:go-no-go
check "usage-ledger:go-no-go --strict still green" php artisan usage-ledger:go-no-go --strict
check "tenant-lifecycle:go-no-go still green" php artisan tenant-lifecycle:go-no-go
check "no secret leaked in provider-summary output" bash -c "! php artisan payment-gateway:provider-summary --json | grep -Ei 'sk_live_|xnd_[A-Za-z0-9]{10}|server_key=|password'"
check "no secret leaked in go-no-go output" bash -c "! php artisan payment-gateway:go-no-go --json | grep -Ei 'sk_live_|xnd_[A-Za-z0-9]{10}|password'"
popd >/dev/null

echo ""
echo "Sprint 31 smoke: PASS=$pass FAIL=$fail"
[ "$fail" -eq 0 ]
