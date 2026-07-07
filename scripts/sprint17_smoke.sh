#!/usr/bin/env bash
#
# Sprint 17 — Pilot Stabilization & Defect Burn-down Foundation smoke test.
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
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 17 evidence exists" test -f docs/sprints/sprint-17-pilot-stabilization-defect-burndown-foundation.md

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
check "PROJECT_RULES has Sprint 16 Pilot Monitoring & Hypercare Foundation Runtime Rule" hasf "Sprint 16 Pilot Monitoring & Hypercare Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 17 Pilot Stabilization & Defect Burn-down Foundation Runtime Rule" hasf "Sprint 17 Pilot Stabilization & Defect Burn-down Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 17" hasf "sprint-17-pilot-stabilization-defect-burndown-foundation.md" docs/PROJECT_RULES.md

echo "== Migrations =="
check "pilot_monitoring_runs migration exists" bash -c "ls $MIG/*create_pilot_monitoring_runs_table.php >/dev/null 2>&1"
check "hypercare_issue_snapshots migration exists" bash -c "ls $MIG/*create_hypercare_issue_snapshots_table.php >/dev/null 2>&1"
check "pilot_defects migration exists" bash -c "ls $MIG/*create_pilot_defects_table.php >/dev/null 2>&1"
check "pilot_defect_events migration exists" bash -c "ls $MIG/*create_pilot_defect_events_table.php >/dev/null 2>&1"

echo "== Models =="
check "PilotMonitoringRun model exists" test -f "$MODELS/PilotMonitoringRun.php"
check "HypercareIssueSnapshot model exists" test -f "$MODELS/HypercareIssueSnapshot.php"
check "PilotDefect model exists" test -f "$MODELS/PilotDefect.php"
check "PilotDefectEvent model exists" test -f "$MODELS/PilotDefectEvent.php"
check "PilotDefect severity/status constants present" hasf "SEVERITY_BLOCKER" "$MODELS/PilotDefect.php"

echo "== Services =="
check "PilotDefectService exists" test -f "$PILOT_SVC/PilotDefectService.php"
check "DefectBurnDownService exists" test -f "$PILOT_SVC/DefectBurnDownService.php"
check "SlaBreachDetectionService exists" test -f "$PILOT_SVC/SlaBreachDetectionService.php"
check "FixVerificationService exists" test -f "$PILOT_SVC/FixVerificationService.php"
check "AcceptedRiskGovernanceService exists" test -f "$PILOT_SVC/AcceptedRiskGovernanceService.php"
check "PilotStabilizationReportService exists" test -f "$PILOT_SVC/PilotStabilizationReportService.php"

echo "== Config =="
check "pilot_stabilization config exists" test -f "$BACK/config/pilot_stabilization.php"
check "pos_foundation lists sprint_17" hasf "sprint_17" "$BACK/config/pos_foundation.php"
check "pos_foundation has defect register rule" hasf "pilot_defect_register_required" "$BACK/config/pos_foundation.php"

echo "== Admin defect APIs =="
check "PilotDefectController exists" test -f "$ADMIN_CTRL/PilotDefectController.php"
check "PilotDefectEventController exists" test -f "$ADMIN_CTRL/PilotDefectEventController.php"
check "PilotDefectBurnDownController exists" test -f "$ADMIN_CTRL/PilotDefectBurnDownController.php"
check "PilotStabilizationReportController exists" test -f "$ADMIN_CTRL/PilotStabilizationReportController.php"
check "IndexPilotDefectRequest exists" test -f "$ADMIN_REQ/IndexPilotDefectRequest.php"
check "StorePilotDefectRequest exists" test -f "$ADMIN_REQ/StorePilotDefectRequest.php"
check "UpdatePilotDefectRequest exists" test -f "$ADMIN_REQ/UpdatePilotDefectRequest.php"
check "AssignPilotDefectRequest exists" test -f "$ADMIN_REQ/AssignPilotDefectRequest.php"
check "TransitionPilotDefectStatusRequest exists" test -f "$ADMIN_REQ/TransitionPilotDefectStatusRequest.php"
check "AcceptPilotDefectRiskRequest exists" test -f "$ADMIN_REQ/AcceptPilotDefectRiskRequest.php"
check "VerifyPilotDefectRequest exists" test -f "$ADMIN_REQ/VerifyPilotDefectRequest.php"
check "PilotDefectResource exists" test -f "$ADMIN_RES/PilotDefectResource.php"
check "PilotDefectEventResource exists" test -f "$ADMIN_RES/PilotDefectEventResource.php"
check "PilotDefectBurnDownResource exists" test -f "$ADMIN_RES/PilotDefectBurnDownResource.php"
check "PilotStabilizationReportResource exists" test -f "$ADMIN_RES/PilotStabilizationReportResource.php"
check "routes register pilot-defects" hasf "pilot-defects" "$BACK/routes/api.php"
check "routes register pilot-stabilization-report" hasf "pilot-stabilization-report" "$BACK/routes/api.php"
check "pilot-defects behind platform.admin group" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Commands =="
check "pilot:defect-summary command exists" test -f "$CMD/PilotDefectSummaryCommand.php"
check "pilot:burndown-summary command exists" test -f "$CMD/PilotBurndownSummaryCommand.php"
check "pilot:sla-check command exists" test -f "$CMD/PilotSlaCheckCommand.php"
check "pilot:stabilization-go-no-go command exists" test -f "$CMD/PilotStabilizationGoNoGoCommand.php"
check "defect-summary supports --json" hasf "json" "$CMD/PilotDefectSummaryCommand.php"
check "burndown-summary supports --json" hasf "json" "$CMD/PilotBurndownSummaryCommand.php"
check "sla-check supports --mark-breached" hasf "mark-breached" "$CMD/PilotSlaCheckCommand.php"
check "stabilization-go-no-go supports --strict" hasf "strict" "$CMD/PilotStabilizationGoNoGoCommand.php"

echo "== Stabilization docs =="
check "defect-register-runbook exists" test -f docs/pilot/defect-register-runbook.md
check "defect-burndown-report exists" test -f docs/pilot/defect-burndown-report.md
check "sla-breach-detection exists" test -f docs/pilot/sla-breach-detection.md
check "accepted-risk-governance exists" test -f docs/pilot/accepted-risk-governance.md
check "fix-verification-retest-workflow exists" test -f docs/pilot/fix-verification-retest-workflow.md
check "stabilization-go-watch-no-go-report exists" test -f docs/pilot/stabilization-go-watch-no-go-report.md
check "stabilization-daily-checklist exists" test -f docs/pilot/stabilization-daily-checklist.md

echo "== Tests =="
check "PilotDefectServiceTest exists" test -f "$TESTS/PilotDefectServiceTest.php"
check "PilotDefectAdminApiTest exists" test -f "$TESTS/PilotDefectAdminApiTest.php"
check "DefectBurnDownServiceTest exists" test -f "$TESTS/DefectBurnDownServiceTest.php"
check "SlaBreachDetectionServiceTest exists" test -f "$TESTS/SlaBreachDetectionServiceTest.php"
check "FixVerificationServiceTest exists" test -f "$TESTS/FixVerificationServiceTest.php"
check "AcceptedRiskGovernanceServiceTest exists" test -f "$TESTS/AcceptedRiskGovernanceServiceTest.php"
check "PilotStabilizationReportServiceTest exists" test -f "$TESTS/PilotStabilizationReportServiceTest.php"
check "PilotStabilizationCommandsTest exists" test -f "$TESTS/PilotStabilizationCommandsTest.php"
check "PilotStabilizationSecurityScanTest exists" test -f "$TESTS/PilotStabilizationSecurityScanTest.php"
check "PilotStabilizationRegressionRouteTest exists" test -f "$TESTS/PilotStabilizationRegressionRouteTest.php"

echo "== Sprint 13/14/15/16 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:uat-summary command intact" hasf "pilot:uat-summary" "$CMD/PilotUatSummaryCommand.php"
check "pilot:deployment-check command intact" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:field-trial-summary command intact" hasf "pilot:field-trial-summary" "$CMD/PilotFieldTrialSummaryCommand.php"
check "pilot:daily-monitoring-check command intact" hasf "pilot:daily-monitoring-check" "$CMD/PilotDailyMonitoringCheckCommand.php"
check "pilot:health-summary command intact" hasf "pilot:health-summary" "$CMD/PilotHealthSummaryCommand.php"
check "hypercare:issue-triage command intact" hasf "hypercare:issue-triage" "$CMD/HypercareIssueTriageCommand.php"

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
check "sprint17-ci workflow exists" test -f .github/workflows/sprint17-ci.yml
check "sprint17-ci runs sprint17 smoke" hasf "sprint17_smoke.sh" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs production:readiness-check" hasf "production:readiness-check" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:uat-summary" hasf "pilot:uat-summary" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:field-trial-summary" hasf "pilot:field-trial-summary" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:daily-monitoring-check" hasf "pilot:daily-monitoring-check" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:health-summary" hasf "pilot:health-summary" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs hypercare:issue-triage" hasf "hypercare:issue-triage" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:defect-summary" hasf "pilot:defect-summary" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:burndown-summary" hasf "pilot:burndown-summary" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:sla-check" hasf "pilot:sla-check" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs pilot:stabilization-go-no-go" hasf "pilot:stabilization-go-no-go" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint17-ci.yml
check "sprint17-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint17-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/onboarding/UAT/deployment/monitoring/hypercare/stabilization panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity\|StabilizationActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
