#!/usr/bin/env bash
#
# Sprint 18 — Pilot Closure & Production Handover Foundation smoke test.
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
SVC="$BACK/app/Services/Handover"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
HND=docs/handover

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 18 evidence exists" test -f docs/sprints/sprint-18-pilot-closure-production-handover-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 17 Pilot Stabilization Runtime Rule" hasf "Sprint 17 Pilot Stabilization & Defect Burn-down Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 18 Pilot Closure Runtime Rule" hasf "Sprint 18 Pilot Closure & Production Handover Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 18" hasf "sprint-18-pilot-closure-production-handover-foundation.md" docs/PROJECT_RULES.md

echo "== Migrations =="
check "pilot_closure_runs migration exists" bash -c "ls $MIG/*create_pilot_closure_runs_table.php >/dev/null 2>&1"
check "production_handover_packages migration exists" bash -c "ls $MIG/*create_production_handover_packages_table.php >/dev/null 2>&1"
check "production_handover_signoffs migration exists" bash -c "ls $MIG/*create_production_handover_signoffs_table.php >/dev/null 2>&1"

echo "== Models =="
check "PilotClosureRun model exists" test -f "$MODELS/PilotClosureRun.php"
check "ProductionHandoverPackage model exists" test -f "$MODELS/ProductionHandoverPackage.php"
check "ProductionHandoverSignoff model exists" test -f "$MODELS/ProductionHandoverSignoff.php"
check "PilotClosureRun status constants present" hasf "STATUS_APPROVED" "$MODELS/PilotClosureRun.php"
check "ProductionHandoverSignoff decision constants present" hasf "DECISION_APPROVED_WITH_RISK" "$MODELS/ProductionHandoverSignoff.php"

echo "== Services =="
check "PilotClosureService exists" test -f "$SVC/PilotClosureService.php"
check "ProductionHandoverService exists" test -f "$SVC/ProductionHandoverService.php"
check "FinalDefectReviewService exists" test -f "$SVC/FinalDefectReviewService.php"
check "AcceptedRiskFinalReviewService exists" test -f "$SVC/AcceptedRiskFinalReviewService.php"
check "ProductionSignoffService exists" test -f "$SVC/ProductionSignoffService.php"
check "ProductionHandoverGoNoGoService exists" test -f "$SVC/ProductionHandoverGoNoGoService.php"

echo "== Config =="
check "production_handover config exists" test -f "$BACK/config/production_handover.php"
check "pos_foundation lists sprint_18" hasf "sprint_18" "$BACK/config/pos_foundation.php"
check "pos_foundation has production handover rule" hasf "production_handover_required" "$BACK/config/pos_foundation.php"

echo "== Admin closure/handover APIs =="
check "PilotClosureController exists" test -f "$ADMIN_CTRL/PilotClosureController.php"
check "ProductionHandoverController exists" test -f "$ADMIN_CTRL/ProductionHandoverController.php"
check "ProductionHandoverSignoffController exists" test -f "$ADMIN_CTRL/ProductionHandoverSignoffController.php"
check "ProductionHandoverGoNoGoController exists" test -f "$ADMIN_CTRL/ProductionHandoverGoNoGoController.php"
check "IndexPilotClosureRequest exists" test -f "$ADMIN_REQ/IndexPilotClosureRequest.php"
check "StorePilotClosureRequest exists" test -f "$ADMIN_REQ/StorePilotClosureRequest.php"
check "ApprovePilotClosureRequest exists" test -f "$ADMIN_REQ/ApprovePilotClosureRequest.php"
check "BlockPilotClosureRequest exists" test -f "$ADMIN_REQ/BlockPilotClosureRequest.php"
check "IndexProductionHandoverRequest exists" test -f "$ADMIN_REQ/IndexProductionHandoverRequest.php"
check "StoreProductionHandoverRequest exists" test -f "$ADMIN_REQ/StoreProductionHandoverRequest.php"
check "UpdateProductionHandoverRequest exists" test -f "$ADMIN_REQ/UpdateProductionHandoverRequest.php"
check "StoreProductionHandoverSignoffRequest exists" test -f "$ADMIN_REQ/StoreProductionHandoverSignoffRequest.php"
check "PilotClosureResource exists" test -f "$ADMIN_RES/PilotClosureResource.php"
check "ProductionHandoverPackageResource exists" test -f "$ADMIN_RES/ProductionHandoverPackageResource.php"
check "ProductionHandoverSignoffResource exists" test -f "$ADMIN_RES/ProductionHandoverSignoffResource.php"
check "ProductionHandoverGoNoGoResource exists" test -f "$ADMIN_RES/ProductionHandoverGoNoGoResource.php"
check "routes register pilot-closures" hasf "pilot-closures" "$BACK/routes/api.php"
check "routes register production-handovers" hasf "production-handovers" "$BACK/routes/api.php"
check "routes register production-handover-go-no-go" hasf "production-handover-go-no-go" "$BACK/routes/api.php"
check "closure/handover behind platform.admin group" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Commands =="
check "pilot:closure-check command exists" test -f "$CMD/PilotClosureCheckCommand.php"
check "production:handover-summary command exists" test -f "$CMD/ProductionHandoverSummaryCommand.php"
check "production:signoff-summary command exists" test -f "$CMD/ProductionSignoffSummaryCommand.php"
check "production:handover-go-no-go command exists" test -f "$CMD/ProductionHandoverGoNoGoCommand.php"
check "closure-check supports --json" hasf "json" "$CMD/PilotClosureCheckCommand.php"
check "handover-summary supports --strict" hasf "strict" "$CMD/ProductionHandoverSummaryCommand.php"
check "signoff-summary supports --json" hasf "json" "$CMD/ProductionSignoffSummaryCommand.php"
check "handover-go-no-go supports --strict" hasf "strict" "$CMD/ProductionHandoverGoNoGoCommand.php"

echo "== Handover docs =="
check "pilot-closure-checklist exists" test -f "$HND/pilot-closure-checklist.md"
check "production-handover-pack exists" test -f "$HND/production-handover-pack.md"
check "operator-admin-handover exists" test -f "$HND/operator-admin-handover.md"
check "final-defect-closure-summary exists" test -f "$HND/final-defect-closure-summary.md"
check "accepted-risk-final-review exists" test -f "$HND/accepted-risk-final-review.md"
check "production-readiness-signoff exists" test -f "$HND/production-readiness-signoff.md"
check "backup-restore-handover exists" test -f "$HND/backup-restore-handover.md"
check "support-sla-handover exists" test -f "$HND/support-sla-handover.md"
check "release-ownership-matrix exists" test -f "$HND/release-ownership-matrix.md"
check "production-go-watch-no-go-report exists" test -f "$HND/production-go-watch-no-go-report.md"
check "ownership matrix has table header" hasf "| Area | Owner Role |" "$HND/release-ownership-matrix.md"

echo "== Tests =="
check "PilotClosureServiceTest exists" test -f "$TESTS/PilotClosureServiceTest.php"
check "ProductionHandoverServiceTest exists" test -f "$TESTS/ProductionHandoverServiceTest.php"
check "FinalDefectReviewServiceTest exists" test -f "$TESTS/FinalDefectReviewServiceTest.php"
check "AcceptedRiskFinalReviewServiceTest exists" test -f "$TESTS/AcceptedRiskFinalReviewServiceTest.php"
check "ProductionSignoffServiceTest exists" test -f "$TESTS/ProductionSignoffServiceTest.php"
check "ProductionHandoverGoNoGoServiceTest exists" test -f "$TESTS/ProductionHandoverGoNoGoServiceTest.php"
check "ProductionHandoverAdminApiTest exists" test -f "$TESTS/ProductionHandoverAdminApiTest.php"
check "ProductionHandoverCommandsTest exists" test -f "$TESTS/ProductionHandoverCommandsTest.php"
check "ProductionHandoverSecurityScanTest exists" test -f "$TESTS/ProductionHandoverSecurityScanTest.php"
check "ProductionHandoverRegressionRouteTest exists" test -f "$TESTS/ProductionHandoverRegressionRouteTest.php"

echo "== Sprint 13-17 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:uat-summary command intact" hasf "pilot:uat-summary" "$CMD/PilotUatSummaryCommand.php"
check "pilot:deployment-check command intact" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:field-trial-summary command intact" hasf "pilot:field-trial-summary" "$CMD/PilotFieldTrialSummaryCommand.php"
check "pilot:daily-monitoring-check command intact" hasf "pilot:daily-monitoring-check" "$CMD/PilotDailyMonitoringCheckCommand.php"
check "pilot:health-summary command intact" hasf "pilot:health-summary" "$CMD/PilotHealthSummaryCommand.php"
check "hypercare:issue-triage command intact" hasf "hypercare:issue-triage" "$CMD/HypercareIssueTriageCommand.php"
check "pilot:stabilization-go-no-go command intact" hasf "pilot:stabilization-go-no-go" "$CMD/PilotStabilizationGoNoGoCommand.php"

echo "== Android release readiness =="
check "android_release_readiness.sh exists" test -f scripts/android_release_readiness.sh
check "android_release_readiness.sh executable" test -x scripts/android_release_readiness.sh
check "Android Gradle wrapper jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts
check "versionCode present" hasf "versionCode" android/app/build.gradle.kts
check "versionName present" hasf "versionName" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint18-ci workflow exists" test -f .github/workflows/sprint18-ci.yml
check "sprint18-ci runs sprint18 smoke" hasf "sprint18_smoke.sh" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs pilot:daily-monitoring-check" hasf "pilot:daily-monitoring-check" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs pilot:stabilization-go-no-go" hasf "pilot:stabilization-go-no-go" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs pilot:closure-check" hasf "pilot:closure-check" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs production:handover-summary" hasf "production:handover-summary" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs production:signoff-summary" hasf "production:signoff-summary" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs production:handover-go-no-go" hasf "production:handover-go-no-go" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint18-ci.yml
check "sprint18-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint18-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/handover/production panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity\|StabilizationActivity\|HandoverActivity\|ProductionActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
