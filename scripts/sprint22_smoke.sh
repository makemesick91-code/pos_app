#!/usr/bin/env bash
#
# Sprint 22 — Lead Management / Sales Pipeline Readiness smoke test.
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
SVC="$BACK/app/Services/SalesPipeline"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
SP=docs/sales-pipeline

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 22 evidence exists" test -f docs/sprints/sprint-22-lead-management-sales-pipeline-readiness-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 21 Runtime Rule" hasf "Sprint 21 Public Website / Landing Page Readiness Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 22 Runtime Rule" hasf "Sprint 22 Lead Management / Sales Pipeline Readiness Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 22" hasf "sprint-22-lead-management-sales-pipeline-readiness-foundation.md" docs/PROJECT_RULES.md

echo "== README =="
check "README has Sprint 22 section" hasf "Sprint 22 — Lead Management / Sales Pipeline Readiness Foundation" README.md

echo "== Migrations =="
check "sales_pipeline_stages migration exists" bash -c "ls $MIG/*create_sales_pipeline_stages_table.php >/dev/null 2>&1"
check "sales_leads migration exists" bash -c "ls $MIG/*create_sales_leads_table.php >/dev/null 2>&1"
check "sales_lead_activities migration exists" bash -c "ls $MIG/*create_sales_lead_activities_table.php >/dev/null 2>&1"
check "sales_lead_assignments migration exists" bash -c "ls $MIG/*create_sales_lead_assignments_table.php >/dev/null 2>&1"
check "sales_pipeline_risks migration exists" bash -c "ls $MIG/*create_sales_pipeline_risks_table.php >/dev/null 2>&1"
check "sales_pipeline_signoffs migration exists" bash -c "ls $MIG/*create_sales_pipeline_signoffs_table.php >/dev/null 2>&1"

echo "== Models =="
check "SalesPipelineStage model exists" test -f "$MODELS/SalesPipelineStage.php"
check "SalesLead model exists" test -f "$MODELS/SalesLead.php"
check "SalesLeadActivity model exists" test -f "$MODELS/SalesLeadActivity.php"
check "SalesLeadAssignment model exists" test -f "$MODELS/SalesLeadAssignment.php"
check "SalesPipelineRisk model exists" test -f "$MODELS/SalesPipelineRisk.php"
check "SalesPipelineSignoff model exists" test -f "$MODELS/SalesPipelineSignoff.php"
check "SalesPipelineRisk severity constants present" hasf "SEVERITY_CRITICAL" "$MODELS/SalesPipelineRisk.php"
check "SalesPipelineSignoff ONBOARDING role present" hasf "ROLE_ONBOARDING" "$MODELS/SalesPipelineSignoff.php"

echo "== Config =="
check "sales_pipeline config exists" test -f "$BACK/config/sales_pipeline.php"
check "pos_foundation lists sprint_22" hasf "sprint_22" "$BACK/config/pos_foundation.php"
check "pos_foundation has sales pipeline rule" hasf "sales_pipeline_readiness_required" "$BACK/config/pos_foundation.php"
check "pos_foundation has no auto tenant rule" hasf "no_auto_tenant_creation_from_lead_sprint_22" "$BACK/config/pos_foundation.php"

echo "== Services =="
check "SalesLeadIntakeService exists" test -f "$SVC/SalesLeadIntakeService.php"
check "SalesPipelineStageService exists" test -f "$SVC/SalesPipelineStageService.php"
check "SalesLeadActivityService exists" test -f "$SVC/SalesLeadActivityService.php"
check "SalesLeadAssignmentService exists" test -f "$SVC/SalesLeadAssignmentService.php"
check "SalesQualificationService exists" test -f "$SVC/SalesQualificationService.php"
check "SalesPipelineRiskGovernanceService exists" test -f "$SVC/SalesPipelineRiskGovernanceService.php"
check "SalesPipelineReadinessService exists" test -f "$SVC/SalesPipelineReadinessService.php"
check "SalesPipelineGoNoGoService exists" test -f "$SVC/SalesPipelineGoNoGoService.php"
check "SanitizesSalesPipelineText trait exists" test -f "$SVC/SanitizesSalesPipelineText.php"

echo "== Requests / controllers / resources =="
check "SalesLeadController exists" test -f "$ADMIN_CTRL/SalesLeadController.php"
check "SalesPipelineStageController exists" test -f "$ADMIN_CTRL/SalesPipelineStageController.php"
check "SalesPipelineRiskController exists" test -f "$ADMIN_CTRL/SalesPipelineRiskController.php"
check "SalesPipelineGoNoGoController exists" test -f "$ADMIN_CTRL/SalesPipelineGoNoGoController.php"
check "StoreSalesLeadRequest exists" test -f "$ADMIN_REQ/StoreSalesLeadRequest.php"
check "SalesLeadResource exists" test -f "$ADMIN_RES/SalesLeadResource.php"

echo "== Commands =="
check "sales-pipeline:readiness command exists" test -f "$CMD/SalesPipelineReadinessCommand.php"
check "sales-pipeline:lead-summary command exists" test -f "$CMD/SalesPipelineLeadSummaryCommand.php"
check "sales-pipeline:activity-summary command exists" test -f "$CMD/SalesPipelineActivitySummaryCommand.php"
check "sales-pipeline:go-no-go command exists" test -f "$CMD/SalesPipelineGoNoGoCommand.php"
check "readiness supports --json" hasf "json" "$CMD/SalesPipelineReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/SalesPipelineGoNoGoCommand.php"

echo "== Sales pipeline docs =="
check "lead-management-policy doc exists" test -f "$SP/lead-management-policy.md"
check "sales-pipeline-stage-map doc exists" test -f "$SP/sales-pipeline-stage-map.md"
check "qualification-readiness-checklist doc exists" test -f "$SP/qualification-readiness-checklist.md"
check "manual-follow-up-playbook doc exists" test -f "$SP/manual-follow-up-playbook.md"
check "onboarding-handover-readiness doc exists" test -f "$SP/onboarding-handover-readiness.md"
check "sales-pipeline-risk-register doc exists" test -f "$SP/sales-pipeline-risk-register.md"
check "sales-pipeline-go-watch-no-go-report doc exists" test -f "$SP/sales-pipeline-go-watch-no-go-report.md"

echo "== Tests =="
check "SalesLeadIntakeServiceTest exists" test -f "$TESTS/SalesLeadIntakeServiceTest.php"
check "SalesPipelineAdminApiTest exists" test -f "$TESTS/SalesPipelineAdminApiTest.php"
check "SalesPipelineCommandsTest exists" test -f "$TESTS/SalesPipelineCommandsTest.php"
check "SalesPipelineSecurityScanTest exists" test -f "$TESTS/SalesPipelineSecurityScanTest.php"
check "SalesPipelineRegressionRouteTest exists" test -f "$TESTS/SalesPipelineRegressionRouteTest.php"

echo "== CI workflow =="
check "sprint22-ci workflow exists" test -f .github/workflows/sprint22-ci.yml
check "sprint22-ci runs sprint22 smoke" hasf "sprint22_smoke.sh" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs public-website:go-no-go" hasf "public-website:go-no-go" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs sales-pipeline:readiness" hasf "sales-pipeline:readiness" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs sales-pipeline:go-no-go" hasf "sales-pipeline:go-no-go" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint22-ci.yml
check "sprint22-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint22-ci.yml

echo "== Security: no CRM/messaging/signup in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET\|CRM_API_KEY\|WHATSAPP_TOKEN" android/app/src/main/java android/app/src/main/res'
check "no Android sales/admin/lead/crm/billing panel" bash -c \
  '! grep -R "SalesPipelineActivity\|LeadManagementActivity\|CRMActivity\|BillingActivity\|AdminActivity" android/app/src/main/java android/app/src/main/res'

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
