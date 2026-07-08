#!/usr/bin/env bash
#
# Sprint 30 — Billing Invoice Generation & Payment Collection Governance
# Foundation smoke test. Structural + command + gate validation; does not build
# the Android app and never charges/deploys.
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
CFG="$BACK/config/billing_governance.php"
SVC="$BACK/app/Services/Billing"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"

echo "== Config / pricing / rules =="
check "billing_governance config exists" test -f "$CFG"
check "default currency IDR" hasf "'default_currency' => 'IDR'" "$CFG"
check "monthly period policy" hasf "'interval' => 'monthly'" "$CFG"
check "starter pricing present" hasf "'starter' =>" "$CFG"
check "partial payments disabled by default" hasf "'allow_partial_payments' => false" "$CFG"
for r in BIL-R001 BIL-R002 BIL-R005 BIL-R010 BIL-R011 BIL-R012 BIL-R013 BIL-R014 BIL-R015 BIL-R016; do
  check "config locks $r" hasf "$r" "$CFG"
done

echo "== Migrations / models =="
check "invoices migration" test -f "$BACK/database/migrations/2026_07_15_990001_create_tenant_billing_invoices_table.php"
check "payments migration" test -f "$BACK/database/migrations/2026_07_15_990002_create_tenant_billing_payments_table.php"
check "unique tenant+period+source index" hasf "tbi_tenant_period_source_unique" "$BACK/database/migrations/2026_07_15_990001_create_tenant_billing_invoices_table.php"
check "TenantBillingInvoice model" test -f "$BACK/app/Models/TenantBillingInvoice.php"
check "TenantBillingPayment model" test -f "$BACK/app/Models/TenantBillingPayment.php"

echo "== Services =="
for s in BillingPeriod BillingPeriodService TenantInvoicePricingService TenantInvoiceNumberGenerator TenantInvoiceStatusService TenantInvoiceService TenantPaymentCollectionService BillingSummaryService BillingMetadataSanitizer BillingAuditService BillingGovernanceAuditService BillingGoNoGoService BillingGovernanceException; do
  check "$s" test -f "$SVC/$s.php"
done

echo "== Commands =="
for c in BillingPeriodSummaryCommand BillingInvoiceGenerateCommand BillingInvoiceSummaryCommand BillingCollectionSummaryCommand BillingGovernanceAuditCommand BillingGoNoGoCommand; do
  check "$c" test -f "$CMD/$c.php"
done
check "invoice-generate is dry-run by default (--apply gate)" hasf "apply : Persist" "$CMD/BillingInvoiceGenerateCommand.php"

echo "== Admin routes (platform.admin) =="
check "AdminBillingInvoiceController" test -f "$ADMIN_CTRL/AdminBillingInvoiceController.php"
check "AdminBillingPaymentController" test -f "$ADMIN_CTRL/AdminBillingPaymentController.php"
check "AdminBillingGovernanceController" test -f "$ADMIN_CTRL/AdminBillingGovernanceController.php"
check "routes register billing/invoices" hasf "billing/invoices" "$BACK/routes/api.php"
check "routes register invoices/generate" hasf "billing/invoices/generate" "$BACK/routes/api.php"
check "routes register payments" hasf "billing/invoices/{invoice}/payments" "$BACK/routes/api.php"
check "routes register mark-failed" hasf "billing/payments/{payment}/mark-failed" "$BACK/routes/api.php"
check "routes register collection-summary" hasf "billing/collection-summary" "$BACK/routes/api.php"
check "no tenant/public billing mutation route" bash -c "! grep -RnE 'tenant-facing|public/billing/(invoices|payments)' $BACK/routes/api.php"

echo "== Rules / foundation lock =="
for r in BIL-R001 BIL-R002 BIL-R011 BIL-R016; do
  check "PROJECT_RULES locks $r" hasf "$r" docs/PROJECT_RULES.md
done
check "pos_foundation locks sprint_30" hasf "sprint_30" "$BACK/config/pos_foundation.php"
check "PROJECT_RULES has Sprint 30 runtime rule" hasf "Sprint 30 Billing Invoice Generation" docs/PROJECT_RULES.md
check "Sprint 29 EGC rules still present" hasf "EGC-R015" docs/PROJECT_RULES.md
check "Sprint 28 ULR rules still present" hasf "ULR-R016" docs/PROJECT_RULES.md
check "Sprint 25 TLS rules still present" hasf "TLS-R004" docs/PROJECT_RULES.md

echo "== Docs =="
check "architecture doc" test -f docs/architecture/billing-invoice-payment-collection-governance.md
check "sprint doc" test -f docs/sprints/sprint-30-billing-invoice-generation-payment-collection-governance-foundation.md
check "architecture doc has dependency graph" hasf "Dependency graph" docs/architecture/billing-invoice-payment-collection-governance.md

echo "== CI grep rules =="
check "BIL-R002 grep target present" bash -c "grep -R 'BIL-R002' $BACK/config docs >/dev/null"
check "BIL-R011 grep target present" bash -c "grep -R 'BIL-R011' $BACK/config docs >/dev/null"
check "BIL-R016 grep target present" bash -c "grep -R 'BIL-R016' $BACK/config docs >/dev/null"

echo "== Runtime gates (in backend) =="
pushd "$BACK" >/dev/null
# Ensure a migrated DB so table-aware gates can evaluate (idempotent).
php artisan migrate --force >/dev/null 2>&1 || true
check "billing:period-summary green" php artisan billing:period-summary
check "billing:invoice-summary green" php artisan billing:invoice-summary
check "billing:collection-summary green" php artisan billing:collection-summary
check "billing:governance-audit green" php artisan billing:governance-audit
check "billing:go-no-go green" php artisan billing:go-no-go
check "export-governance:go-no-go still green" php artisan export-governance:go-no-go
check "usage-ledger:go-no-go --strict still green" php artisan usage-ledger:go-no-go --strict
check "report-export-metering:go-no-go still green" php artisan report-export-metering:go-no-go
check "tenant-plan:go-no-go still green" php artisan tenant-plan:go-no-go
check "tenant-lifecycle:go-no-go still green" php artisan tenant-lifecycle:go-no-go
check "no secret leaked in go-no-go output" bash -c "! php artisan billing:go-no-go --json | grep -Ei 'password|secret|server_key|client_key'"
popd >/dev/null

echo ""
echo "Sprint 30 smoke: PASS=$pass FAIL=$fail"
[ "$fail" -eq 0 ]
