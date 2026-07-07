#!/usr/bin/env bash
#
# Sprint 15 — Pilot Deployment & Field Trial Evidence Foundation smoke test.
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
PILOT_SVC="$BACK/app/Services/Pilot"
CMD="$BACK/app/Console/Commands"

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 15 evidence exists" test -f docs/sprints/sprint-15-pilot-deployment-field-trial-evidence-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 1 Multi-Tenant Runtime Rule" hasf "Sprint 1 Multi-Tenant Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 2 Product Foundation Runtime Rule" hasf "Sprint 2 Product Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 3 Android Cashier Foundation Runtime Rule" hasf "Sprint 3 Android Cashier Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 4 Sales Backend Integration Runtime Rule" hasf "Sprint 4 Sales Backend Integration Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" hasf "Sprint 5 QRIS Payment Gateway Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 6 Printer & Receipt Foundation Runtime Rule" hasf "Sprint 6 Printer & Receipt Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 7 Offline Cash & Sync Foundation Runtime Rule" hasf "Sprint 7 Offline Cash & Sync Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 8 Inventory Simple Foundation Runtime Rule" hasf "Sprint 8 Inventory Simple Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 9 Reports & Closing Foundation Runtime Rule" hasf "Sprint 9 Reports & Closing Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 10 Subscription & Device Limit Foundation Runtime Rule" hasf "Sprint 10 Subscription & Device Limit Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 11 Admin SaaS Control Panel Foundation Runtime Rule" hasf "Sprint 11 Admin SaaS Control Panel Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 12 Tenant Onboarding & Demo Data Foundation Runtime Rule" hasf "Sprint 12 Tenant Onboarding & Demo Data Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 13 Production Readiness & Release Hardening Foundation Runtime Rule" hasf "Sprint 13 Production Readiness & Release Hardening Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 14 Pilot Release Candidate & Operator UAT Foundation Runtime Rule" hasf "Sprint 14 Pilot Release Candidate & Operator UAT Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 15 Pilot Deployment & Field Trial Evidence Foundation Runtime Rule" hasf "Sprint 15 Pilot Deployment & Field Trial Evidence Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 15" hasf "sprint-15-pilot-deployment-field-trial-evidence-foundation.md" docs/PROJECT_RULES.md

echo "== Pilot deployment / field docs =="
check "pilot-deployment-checklist exists" test -f docs/pilot/pilot-deployment-checklist.md
check "field-trial-evidence-pack exists" test -f docs/pilot/field-trial-evidence-pack.md
check "backend-deployment-dry-run exists" test -f docs/pilot/backend-deployment-dry-run.md
check "android-rc-artifact-handling exists" test -f docs/pilot/android-rc-artifact-handling.md
check "operator-device-readiness exists" test -f docs/pilot/operator-device-readiness.md
check "demo-tenant-pilot-setup-evidence exists" test -f docs/pilot/demo-tenant-pilot-setup-evidence.md
check "post-deploy-smoke-checklist exists" test -f docs/pilot/post-deploy-smoke-checklist.md
check "pilot-rollback-checklist exists" test -f docs/pilot/pilot-rollback-checklist.md
check "daily-pilot-monitoring-checklist exists" test -f docs/pilot/daily-pilot-monitoring-checklist.md
check "field-issue-register exists" test -f docs/pilot/field-issue-register.md
check "field-trial-go-watch-no-go-report exists" test -f docs/pilot/field-trial-go-watch-no-go-report.md

echo "== Pilot services & commands =="
check "PilotDeploymentReadinessService exists" test -f "$PILOT_SVC/PilotDeploymentReadinessService.php"
check "FieldTrialEvidenceService exists" test -f "$PILOT_SVC/FieldTrialEvidenceService.php"
check "pilot:deployment-check command exists" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:field-trial-summary command exists" hasf "pilot:field-trial-summary" "$CMD/PilotFieldTrialSummaryCommand.php"
check "pilot_deployment config exists" test -f "$BACK/config/pilot_deployment.php"

echo "== Config lock =="
check "pos_foundation lists sprint_15" hasf "sprint_15" "$BACK/config/pos_foundation.php"
check "pos_foundation pilot deployment gate flag" hasf "pilot_deployment_gate_required" "$BACK/config/pos_foundation.php"
check "pos_foundation field trial evidence flag" hasf "field_trial_evidence_required" "$BACK/config/pos_foundation.php"
check "pos_foundation rollback checklist flag" hasf "pilot_rollback_checklist_required" "$BACK/config/pos_foundation.php"
check "pos_foundation field issue register flag" hasf "field_issue_register_required" "$BACK/config/pos_foundation.php"
check "pos_foundation no auto deploy sprint 15 flag" hasf "no_auto_production_deploy_sprint_15" "$BACK/config/pos_foundation.php"

echo "== Sprint 15 tests =="
check "PilotDeploymentReadinessServiceTest exists" test -f "$BACK/tests/Feature/PilotDeploymentReadinessServiceTest.php"
check "PilotDeploymentCheckCommandTest exists" test -f "$BACK/tests/Feature/PilotDeploymentCheckCommandTest.php"
check "FieldTrialEvidenceServiceTest exists" test -f "$BACK/tests/Feature/FieldTrialEvidenceServiceTest.php"
check "PilotFieldTrialSummaryCommandTest exists" test -f "$BACK/tests/Feature/PilotFieldTrialSummaryCommandTest.php"
check "PilotDeploymentSecurityScanTest exists" test -f "$BACK/tests/Feature/PilotDeploymentSecurityScanTest.php"
check "PilotDeploymentRegressionRouteTest exists" test -f "$BACK/tests/Feature/PilotDeploymentRegressionRouteTest.php"

echo "== Sprint 13/14 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:uat-summary command intact" hasf "pilot:uat-summary" "$CMD/PilotUatSummaryCommand.php"
check "ReleaseGateService intact" test -f "$BACK/app/Services/Release/ReleaseGateService.php"
check "PilotReleaseCandidateService intact" test -f "$PILOT_SVC/PilotReleaseCandidateService.php"

echo "== Android release readiness =="
check "android_release_readiness.sh exists" test -f scripts/android_release_readiness.sh
check "android_release_readiness.sh executable" test -x scripts/android_release_readiness.sh
check "Android Gradle wrapper jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "android gradlew executable" test -x android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts
check "versionCode present" hasf "versionCode" android/app/build.gradle.kts
check "versionName present" hasf "versionName" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint15-ci workflow exists" test -f .github/workflows/sprint15-ci.yml
check "sprint15-ci runs sprint15 smoke" hasf "sprint15_smoke.sh" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs production:readiness-check" hasf "production:readiness-check" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs pilot:uat-summary" hasf "pilot:uat-summary" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs pilot:field-trial-summary" hasf "pilot:field-trial-summary" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint15-ci.yml
check "sprint15-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint15-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/onboarding/UAT/deployment panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
