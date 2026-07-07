#!/usr/bin/env bash
#
# Sprint 16 — Pilot Monitoring & Hypercare Foundation smoke test.
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
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 16 evidence exists" test -f docs/sprints/sprint-16-pilot-monitoring-hypercare-foundation.md

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
check "PROJECT_RULES lock index lists sprint 16" hasf "sprint-16-pilot-monitoring-hypercare-foundation.md" docs/PROJECT_RULES.md

echo "== Pilot monitoring / hypercare docs =="
check "daily-monitoring-runbook exists" test -f docs/pilot/daily-monitoring-runbook.md
check "hypercare-issue-triage-workflow exists" test -f docs/pilot/hypercare-issue-triage-workflow.md
check "field-issue-severity-sla exists" test -f docs/pilot/field-issue-severity-sla.md
check "operator-feedback-log exists" test -f docs/pilot/operator-feedback-log.md
check "pilot-health-summary-template exists" test -f docs/pilot/pilot-health-summary-template.md
check "hypercare-go-watch-no-go-report exists" test -f docs/pilot/hypercare-go-watch-no-go-report.md
check "failed-sync-monitoring-checklist exists" test -f docs/pilot/failed-sync-monitoring-checklist.md
check "payment-qris-monitoring-checklist exists" test -f docs/pilot/payment-qris-monitoring-checklist.md
check "device-subscription-anomaly-checklist exists" test -f docs/pilot/device-subscription-anomaly-checklist.md
check "closing-report-monitoring-checklist exists" test -f docs/pilot/closing-report-monitoring-checklist.md

echo "== Pilot services & commands =="
check "PilotMonitoringService exists" test -f "$PILOT_SVC/PilotMonitoringService.php"
check "PilotHealthSummaryService exists" test -f "$PILOT_SVC/PilotHealthSummaryService.php"
check "HypercareIssueTriageService exists" test -f "$PILOT_SVC/HypercareIssueTriageService.php"
check "pilot:daily-monitoring-check command exists" hasf "pilot:daily-monitoring-check" "$CMD/PilotDailyMonitoringCheckCommand.php"
check "pilot:health-summary command exists" hasf "pilot:health-summary" "$CMD/PilotHealthSummaryCommand.php"
check "hypercare:issue-triage command exists" hasf "hypercare:issue-triage" "$CMD/HypercareIssueTriageCommand.php"
check "pilot_monitoring config exists" test -f "$BACK/config/pilot_monitoring.php"

echo "== Config lock =="
check "pos_foundation lists sprint_16" hasf "sprint_16" "$BACK/config/pos_foundation.php"
check "pos_foundation pilot monitoring gate flag" hasf "pilot_monitoring_gate_required" "$BACK/config/pos_foundation.php"
check "pos_foundation hypercare issue triage flag" hasf "hypercare_issue_triage_required" "$BACK/config/pos_foundation.php"
check "pos_foundation daily health summary flag" hasf "daily_pilot_health_summary_required" "$BACK/config/pos_foundation.php"
check "pos_foundation operator feedback log flag" hasf "operator_feedback_log_required" "$BACK/config/pos_foundation.php"
check "pos_foundation no real alert flag" hasf "no_real_alert_sending_sprint_16" "$BACK/config/pos_foundation.php"

echo "== Sprint 16 tests =="
check "PilotMonitoringServiceTest exists" test -f "$BACK/tests/Feature/PilotMonitoringServiceTest.php"
check "PilotDailyMonitoringCheckCommandTest exists" test -f "$BACK/tests/Feature/PilotDailyMonitoringCheckCommandTest.php"
check "PilotHealthSummaryServiceTest exists" test -f "$BACK/tests/Feature/PilotHealthSummaryServiceTest.php"
check "PilotHealthSummaryCommandTest exists" test -f "$BACK/tests/Feature/PilotHealthSummaryCommandTest.php"
check "HypercareIssueTriageServiceTest exists" test -f "$BACK/tests/Feature/HypercareIssueTriageServiceTest.php"
check "HypercareIssueTriageCommandTest exists" test -f "$BACK/tests/Feature/HypercareIssueTriageCommandTest.php"
check "PilotMonitoringSecurityScanTest exists" test -f "$BACK/tests/Feature/PilotMonitoringSecurityScanTest.php"
check "PilotMonitoringRegressionRouteTest exists" test -f "$BACK/tests/Feature/PilotMonitoringRegressionRouteTest.php"

echo "== Sprint 13/14/15 foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:rc-check command intact" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:uat-summary command intact" hasf "pilot:uat-summary" "$CMD/PilotUatSummaryCommand.php"
check "pilot:deployment-check command intact" hasf "pilot:deployment-check" "$CMD/PilotDeploymentCheckCommand.php"
check "pilot:field-trial-summary command intact" hasf "pilot:field-trial-summary" "$CMD/PilotFieldTrialSummaryCommand.php"
check "PilotDeploymentReadinessService intact" test -f "$PILOT_SVC/PilotDeploymentReadinessService.php"
check "FieldTrialEvidenceService intact" test -f "$PILOT_SVC/FieldTrialEvidenceService.php"

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
check "sprint16-ci workflow exists" test -f .github/workflows/sprint16-ci.yml
check "sprint16-ci runs sprint16 smoke" hasf "sprint16_smoke.sh" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs production:readiness-check" hasf "production:readiness-check" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:uat-summary" hasf "pilot:uat-summary" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:deployment-check" hasf "pilot:deployment-check" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:field-trial-summary" hasf "pilot:field-trial-summary" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:daily-monitoring-check" hasf "pilot:daily-monitoring-check" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs pilot:health-summary" hasf "pilot:health-summary" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs hypercare:issue-triage" hasf "hypercare:issue-triage" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint16-ci.yml
check "sprint16-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint16-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/onboarding/UAT/deployment/monitoring/hypercare panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity\|DeploymentActivity\|MonitoringActivity\|HypercareActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
