#!/usr/bin/env bash
#
# Sprint 20 — Commercial Launch Readiness & SaaS Packaging smoke test.
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
SVC="$BACK/app/Services/Commercial"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
COMM=docs/commercial

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 20 evidence exists" test -f docs/sprints/sprint-20-commercial-launch-readiness-saas-packaging-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 19 Runtime Rule" hasf "Sprint 19 Production Operations Baseline & Post-Handover Governance Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 20 Runtime Rule" hasf "Sprint 20 Commercial Launch Readiness & SaaS Packaging Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 20" hasf "sprint-20-commercial-launch-readiness-saas-packaging-foundation.md" docs/PROJECT_RULES.md

echo "== Migrations =="
check "commercial_launch_runs migration exists" bash -c "ls $MIG/*create_commercial_launch_runs_table.php >/dev/null 2>&1"
check "saas_package_catalogs migration exists" bash -c "ls $MIG/*create_saas_package_catalogs_table.php >/dev/null 2>&1"
check "commercial_launch_signoffs migration exists" bash -c "ls $MIG/*create_commercial_launch_signoffs_table.php >/dev/null 2>&1"
check "commercial_launch_risks migration exists" bash -c "ls $MIG/*create_commercial_launch_risks_table.php >/dev/null 2>&1"

echo "== Models =="
check "CommercialLaunchRun model exists" test -f "$MODELS/CommercialLaunchRun.php"
check "SaasPackageCatalog model exists" test -f "$MODELS/SaasPackageCatalog.php"
check "CommercialLaunchSignoff model exists" test -f "$MODELS/CommercialLaunchSignoff.php"
check "CommercialLaunchRisk model exists" test -f "$MODELS/CommercialLaunchRisk.php"
check "CommercialLaunchRun status constants present" hasf "STATUS_BLOCKED" "$MODELS/CommercialLaunchRun.php"
check "SaasPackageCatalog status constants present" hasf "STATUS_ACTIVE" "$MODELS/SaasPackageCatalog.php"
check "CommercialLaunchRisk severity constants present" hasf "SEVERITY_CRITICAL" "$MODELS/CommercialLaunchRisk.php"
check "CommercialLaunchSignoff decision constants present" hasf "DECISION_APPROVED_WITH_RISK" "$MODELS/CommercialLaunchSignoff.php"

echo "== Services =="
check "CommercialLaunchReadinessService exists" test -f "$SVC/CommercialLaunchReadinessService.php"
check "SaaSPackageCatalogService exists" test -f "$SVC/SaaSPackageCatalogService.php"
check "PricingPlanGovernanceService exists" test -f "$SVC/PricingPlanGovernanceService.php"
check "SalesEnablementReadinessService exists" test -f "$SVC/SalesEnablementReadinessService.php"
check "OnboardingCapacityService exists" test -f "$SVC/OnboardingCapacityService.php"
check "CommercialRiskGovernanceService exists" test -f "$SVC/CommercialRiskGovernanceService.php"
check "CommercialLaunchGoNoGoService exists" test -f "$SVC/CommercialLaunchGoNoGoService.php"

echo "== Config =="
check "commercial_launch config exists" test -f "$BACK/config/commercial_launch.php"
check "pos_foundation lists sprint_20" hasf "sprint_20" "$BACK/config/pos_foundation.php"
check "pos_foundation has commercial launch rule" hasf "commercial_launch_readiness_required" "$BACK/config/pos_foundation.php"
check "pos_foundation has no public signup rule" hasf "no_public_signup_sprint_20" "$BACK/config/pos_foundation.php"

echo "== Admin commercial APIs =="
check "CommercialLaunchRunController exists" test -f "$ADMIN_CTRL/CommercialLaunchRunController.php"
check "SaasPackageCatalogController exists" test -f "$ADMIN_CTRL/SaasPackageCatalogController.php"
check "CommercialLaunchRiskController exists" test -f "$ADMIN_CTRL/CommercialLaunchRiskController.php"
check "CommercialLaunchSignoffController exists" test -f "$ADMIN_CTRL/CommercialLaunchSignoffController.php"
check "CommercialLaunchReadinessController exists" test -f "$ADMIN_CTRL/CommercialLaunchReadinessController.php"
check "CommercialPackageSummaryController exists" test -f "$ADMIN_CTRL/CommercialPackageSummaryController.php"
check "CommercialOnboardingCapacityController exists" test -f "$ADMIN_CTRL/CommercialOnboardingCapacityController.php"
check "CommercialLaunchGoNoGoController exists" test -f "$ADMIN_CTRL/CommercialLaunchGoNoGoController.php"
check "StoreSaasPackageCatalogRequest exists" test -f "$ADMIN_REQ/StoreSaasPackageCatalogRequest.php"
check "StoreCommercialLaunchRiskRequest exists" test -f "$ADMIN_REQ/StoreCommercialLaunchRiskRequest.php"
check "AcceptCommercialLaunchRiskRequest exists" test -f "$ADMIN_REQ/AcceptCommercialLaunchRiskRequest.php"
check "StoreCommercialLaunchSignoffRequest exists" test -f "$ADMIN_REQ/StoreCommercialLaunchSignoffRequest.php"
check "SaasPackageCatalogResource exists" test -f "$ADMIN_RES/SaasPackageCatalogResource.php"
check "CommercialLaunchGoNoGoResource exists" test -f "$ADMIN_RES/CommercialLaunchGoNoGoResource.php"
check "routes register commercial-launch-runs" hasf "commercial-launch-runs" "$BACK/routes/api.php"
check "routes register saas-packages" hasf "saas-packages" "$BACK/routes/api.php"
check "routes register commercial-risks" hasf "commercial-risks" "$BACK/routes/api.php"
check "routes register commercial-launch-go-no-go" hasf "commercial-launch-go-no-go" "$BACK/routes/api.php"
check "commercial behind platform.admin group" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Commands =="
check "commercial:launch-readiness command exists" test -f "$CMD/CommercialLaunchReadinessCommand.php"
check "commercial:package-summary command exists" test -f "$CMD/CommercialPackageSummaryCommand.php"
check "commercial:onboarding-capacity command exists" test -f "$CMD/CommercialOnboardingCapacityCommand.php"
check "commercial:launch-go-no-go command exists" test -f "$CMD/CommercialLaunchGoNoGoCommand.php"
check "launch-readiness supports --json" hasf "json" "$CMD/CommercialLaunchReadinessCommand.php"
check "package-summary supports --strict" hasf "strict" "$CMD/CommercialPackageSummaryCommand.php"
check "onboarding-capacity supports --json" hasf "json" "$CMD/CommercialOnboardingCapacityCommand.php"
check "launch-go-no-go supports --strict" hasf "strict" "$CMD/CommercialLaunchGoNoGoCommand.php"

echo "== Commercial docs =="
check "commercial-launch-checklist exists" test -f "$COMM/commercial-launch-checklist.md"
check "saas-package-catalog exists" test -f "$COMM/saas-package-catalog.md"
check "pricing-plan-governance exists" test -f "$COMM/pricing-plan-governance.md"
check "sales-enablement-pack exists" test -f "$COMM/sales-enablement-pack.md"
check "customer-onboarding-capacity exists" test -f "$COMM/customer-onboarding-capacity.md"
check "commercial-risk-register exists" test -f "$COMM/commercial-risk-register.md"
check "launch-signoff exists" test -f "$COMM/launch-signoff.md"
check "commercial-go-watch-no-go-report exists" test -f "$COMM/commercial-go-watch-no-go-report.md"
check "no-public-signup-no-real-billing-policy exists" test -f "$COMM/no-public-signup-no-real-billing-policy.md"

echo "== Tests =="
check "SaaSPackageCatalogServiceTest exists" test -f "$TESTS/SaaSPackageCatalogServiceTest.php"
check "CommercialRiskGovernanceServiceTest exists" test -f "$TESTS/CommercialRiskGovernanceServiceTest.php"
check "CommercialLaunchReadinessServiceTest exists" test -f "$TESTS/CommercialLaunchReadinessServiceTest.php"
check "CommercialLaunchGoNoGoServiceTest exists" test -f "$TESTS/CommercialLaunchGoNoGoServiceTest.php"
check "CommercialLaunchAdminApiTest exists" test -f "$TESTS/CommercialLaunchAdminApiTest.php"
check "CommercialLaunchCommandsTest exists" test -f "$TESTS/CommercialLaunchCommandsTest.php"
check "CommercialLaunchSecurityScanTest exists" test -f "$TESTS/CommercialLaunchSecurityScanTest.php"
check "CommercialLaunchRegressionRouteTest exists" test -f "$TESTS/CommercialLaunchRegressionRouteTest.php"

echo "== Sprint 13-19 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:deployment-check command intact" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:daily-monitoring-check command intact" hasf "pilot:daily-monitoring-check" "$CMD/PilotDailyMonitoringCheckCommand.php"
check "pilot:stabilization-go-no-go command intact" hasf "pilot:stabilization-go-no-go" "$CMD/PilotStabilizationGoNoGoCommand.php"
check "pilot:closure-check command intact" hasf "pilot:closure-check" "$CMD/PilotClosureCheckCommand.php"
check "production:handover-go-no-go command intact" hasf "production:handover-go-no-go" "$CMD/ProductionHandoverGoNoGoCommand.php"
check "production:post-handover-go-no-go command intact" hasf "production:post-handover-go-no-go" "$CMD/ProductionPostHandoverGoNoGoCommand.php"

echo "== Android release readiness =="
check "android_release_readiness.sh exists" test -f scripts/android_release_readiness.sh
check "android_release_readiness.sh executable" test -x scripts/android_release_readiness.sh
check "Android Gradle wrapper jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint20-ci workflow exists" test -f .github/workflows/sprint20-ci.yml
check "sprint20-ci runs sprint20 smoke" hasf "sprint20_smoke.sh" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs pilot:daily-monitoring-check" hasf "pilot:daily-monitoring-check" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs pilot:stabilization-go-no-go" hasf "pilot:stabilization-go-no-go" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs pilot:closure-check" hasf "pilot:closure-check" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs production:handover-go-no-go" hasf "production:handover-go-no-go" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs production:post-handover-go-no-go" hasf "production:post-handover-go-no-go" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs commercial:launch-readiness" hasf "commercial:launch-readiness" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs commercial:package-summary" hasf "commercial:package-summary" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs commercial:onboarding-capacity" hasf "commercial:onboarding-capacity" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs commercial:launch-go-no-go" hasf "commercial:launch-go-no-go" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint20-ci.yml
check "sprint20-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint20-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/commercial/launch panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|CommercialActivity\|LaunchActivity\|PackageActivity\|PricingActivity\|SignupActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
