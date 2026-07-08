#!/usr/bin/env bash
#
# Sprint 23 — Billing Collection Governance Foundation smoke test.
# Structural validation only; does not build the Android app or run a database.
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
SVC="$BACK/app/Services/BillingCollection"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
BC=docs/billing-collection

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 23 evidence exists" test -f docs/sprints/sprint-23-billing-collection-governance-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 22 Runtime Rule" hasf "Sprint 22 Lead Management / Sales Pipeline Readiness Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 23 Runtime Rule" hasf "Sprint 23 Billing Collection Governance Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 23" hasf "sprint-23-billing-collection-governance-foundation.md" docs/PROJECT_RULES.md

echo "== README =="
check "README has Sprint 23 section" hasf "Sprint 23 — Billing Collection Governance Foundation" README.md

echo "== Migrations =="
check "saas_billing_accounts migration exists" bash -c "ls $MIG/*create_saas_billing_accounts_table.php >/dev/null 2>&1"
check "saas_billing_cycles migration exists" bash -c "ls $MIG/*create_saas_billing_cycles_table.php >/dev/null 2>&1"
check "saas_billing_invoices migration exists" bash -c "ls $MIG/*create_saas_billing_invoices_table.php >/dev/null 2>&1"
check "saas_billing_invoice_lines migration exists" bash -c "ls $MIG/*create_saas_billing_invoice_lines_table.php >/dev/null 2>&1"
check "saas_billing_payment_evidences migration exists" bash -c "ls $MIG/*create_saas_billing_payment_evidences_table.php >/dev/null 2>&1"
check "saas_billing_collection_activities migration exists" bash -c "ls $MIG/*create_saas_billing_collection_activities_table.php >/dev/null 2>&1"
check "saas_billing_collection_risks migration exists" bash -c "ls $MIG/*create_saas_billing_collection_risks_table.php >/dev/null 2>&1"
check "saas_billing_collection_signoffs migration exists" bash -c "ls $MIG/*create_saas_billing_collection_signoffs_table.php >/dev/null 2>&1"

echo "== Models =="
for m in SaasBillingAccount SaasBillingCycle SaasBillingInvoice SaasBillingInvoiceLine SaasBillingPaymentEvidence SaasBillingCollectionActivity SaasBillingCollectionRisk SaasBillingCollectionSignoff; do
  check "$m model exists" test -f "$MODELS/$m.php"
done

echo "== Config =="
check "billing_collection config exists" test -f "$BACK/config/billing_collection.php"
check "pos_foundation lists sprint_23" hasf "sprint_23" "$BACK/config/pos_foundation.php"
check "pos_foundation has billing rule" hasf "billing_collection_governance_required" "$BACK/config/pos_foundation.php"
check "pos_foundation has no auto charge rule" hasf "no_auto_charge_sprint_23" "$BACK/config/pos_foundation.php"

echo "== Services =="
for s in BillingAccountService BillingCycleService BillingInvoiceService BillingPaymentEvidenceService BillingCollectionActivityService BillingCollectionRiskGovernanceService BillingCollectionReadinessService BillingCollectionGoNoGoService SanitizesBillingCollectionText; do
  check "$s exists" test -f "$SVC/$s.php"
done

echo "== Requests / controllers / resources =="
check "BillingAccountController exists" test -f "$ADMIN_CTRL/BillingAccountController.php"
check "BillingInvoiceController exists" test -f "$ADMIN_CTRL/BillingInvoiceController.php"
check "BillingPaymentEvidenceController exists" test -f "$ADMIN_CTRL/BillingPaymentEvidenceController.php"
check "BillingCollectionGoNoGoController exists" test -f "$ADMIN_CTRL/BillingCollectionGoNoGoController.php"
check "StoreBillingInvoiceRequest exists" test -f "$ADMIN_REQ/StoreBillingInvoiceRequest.php"
check "StoreBillingPaymentEvidenceRequest exists" test -f "$ADMIN_REQ/StoreBillingPaymentEvidenceRequest.php"
check "BillingInvoiceResource exists" test -f "$ADMIN_RES/BillingInvoiceResource.php"
check "BillingCollectionGoNoGoResource exists" test -f "$ADMIN_RES/BillingCollectionGoNoGoResource.php"

echo "== Commands =="
check "billing-collection:readiness command exists" test -f "$CMD/BillingCollectionReadinessCommand.php"
check "billing-collection:invoice-summary command exists" test -f "$CMD/BillingCollectionInvoiceSummaryCommand.php"
check "billing-collection:collection-summary command exists" test -f "$CMD/BillingCollectionCollectionSummaryCommand.php"
check "billing-collection:go-no-go command exists" test -f "$CMD/BillingCollectionGoNoGoCommand.php"
check "readiness supports --json" hasf "json" "$CMD/BillingCollectionReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/BillingCollectionGoNoGoCommand.php"

echo "== Billing collection docs =="
check "billing-collection-policy doc exists" test -f "$BC/billing-collection-policy.md"
check "manual-payment-evidence-policy doc exists" test -f "$BC/manual-payment-evidence-policy.md"
check "invoice-lifecycle-map doc exists" test -f "$BC/invoice-lifecycle-map.md"
check "manual-collection-playbook doc exists" test -f "$BC/manual-collection-playbook.md"
check "overdue-dispute-governance doc exists" test -f "$BC/overdue-dispute-governance.md"
check "billing-risk-register doc exists" test -f "$BC/billing-risk-register.md"
check "billing-collection-go-watch-no-go-report doc exists" test -f "$BC/billing-collection-go-watch-no-go-report.md"

echo "== Tests =="
check "BillingInvoiceServiceTest exists" test -f "$TESTS/BillingInvoiceServiceTest.php"
check "BillingPaymentEvidenceServiceTest exists" test -f "$TESTS/BillingPaymentEvidenceServiceTest.php"
check "BillingCollectionAdminApiTest exists" test -f "$TESTS/BillingCollectionAdminApiTest.php"
check "BillingCollectionCommandsTest exists" test -f "$TESTS/BillingCollectionCommandsTest.php"
check "BillingCollectionSecurityScanTest exists" test -f "$TESTS/BillingCollectionSecurityScanTest.php"
check "BillingCollectionRegressionRouteTest exists" test -f "$TESTS/BillingCollectionRegressionRouteTest.php"

echo "== CI workflow =="
check "sprint23-ci workflow exists" test -f .github/workflows/sprint23-ci.yml
check "sprint23-ci runs sprint23 smoke" hasf "sprint23_smoke.sh" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs sales-pipeline:go-no-go" hasf "sales-pipeline:go-no-go" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs billing-collection:readiness" hasf "billing-collection:readiness" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs billing-collection:go-no-go" hasf "billing-collection:go-no-go" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint23-ci.yml
check "sprint23-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint23-ci.yml

echo "== Security: no billing/CRM/gateway UI in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|CRM_API_KEY\|WHATSAPP_TOKEN" android/app/src/main/java android/app/src/main/res'
check "no Android billing/admin/CRM panel" bash -c \
  '! grep -R "BillingActivity\|BillingCollectionActivity\|InvoiceActivity\|PaymentEvidenceActivity\|AdminBillingActivity\|CRMActivity\|AccountingActivity\|TenantSuspensionActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo "== Android package/SDK intact =="
check "Android package intact" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "Android minSdk 26 intact" hasf "minSdk = 26" android/app/build.gradle.kts
check "Android targetSdk 35 intact" hasf "targetSdk = 35" android/app/build.gradle.kts

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
