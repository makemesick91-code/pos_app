#!/usr/bin/env bash
#
# Sprint 14 — Pilot Release Candidate & Operator UAT Foundation smoke test.
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
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 14 evidence exists" test -f docs/sprints/sprint-14-pilot-release-candidate-operator-uat-foundation.md

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
check "PROJECT_RULES lock index lists sprint 14" hasf "sprint-14-pilot-release-candidate-operator-uat-foundation.md" docs/PROJECT_RULES.md

echo "== Pilot docs =="
check "pilot-rc-checklist exists" test -f docs/pilot/pilot-rc-checklist.md
check "operator-uat-checklist exists" test -f docs/pilot/operator-uat-checklist.md
check "smoke-scenario-pack exists" test -f docs/pilot/smoke-scenario-pack.md
check "issue-register exists" test -f docs/pilot/issue-register.md
check "uat-result-template exists" test -f docs/pilot/uat-result-template.md
check "rc-go-watch-no-go-evidence exists" test -f docs/pilot/rc-go-watch-no-go-evidence.md

echo "== Pilot services & commands =="
check "PilotReleaseCandidateService exists" test -f "$PILOT_SVC/PilotReleaseCandidateService.php"
check "OperatorUatSummaryService exists" test -f "$PILOT_SVC/OperatorUatSummaryService.php"
check "pilot:rc-check command exists" hasf "pilot:rc-check" "$CMD/PilotRcCheckCommand.php"
check "pilot:uat-summary command exists" hasf "pilot:uat-summary" "$CMD/PilotUatSummaryCommand.php"
check "pilot_uat config exists" test -f "$BACK/config/pilot_uat.php"

echo "== Config lock =="
check "pos_foundation lists sprint_14" hasf "sprint_14" "$BACK/config/pos_foundation.php"
check "pos_foundation pilot rc gate flag" hasf "pilot_rc_gate_required" "$BACK/config/pos_foundation.php"
check "pos_foundation operator uat flag" hasf "operator_uat_checklist_required" "$BACK/config/pos_foundation.php"
check "pos_foundation pilot issue register flag" hasf "pilot_issue_register_required" "$BACK/config/pos_foundation.php"
check "pos_foundation rc decision flag" hasf "rc_go_watch_no_go_required" "$BACK/config/pos_foundation.php"
check "pos_foundation no auto deploy sprint 14 flag" hasf "no_auto_production_deploy_sprint_14" "$BACK/config/pos_foundation.php"

echo "== Pilot tests =="
check "PilotReleaseCandidateServiceTest exists" test -f "$BACK/tests/Feature/PilotReleaseCandidateServiceTest.php"
check "PilotRcCheckCommandTest exists" test -f "$BACK/tests/Feature/PilotRcCheckCommandTest.php"
check "OperatorUatSummaryServiceTest exists" test -f "$BACK/tests/Feature/OperatorUatSummaryServiceTest.php"
check "PilotUatSummaryCommandTest exists" test -f "$BACK/tests/Feature/PilotUatSummaryCommandTest.php"
check "PilotReleaseSecurityScanTest exists" test -f "$BACK/tests/Feature/PilotReleaseSecurityScanTest.php"
check "PilotReleaseRegressionRouteTest exists" test -f "$BACK/tests/Feature/PilotReleaseRegressionRouteTest.php"

echo "== Sprint 13 release foundation intact =="
check "production:readiness-check command intact" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "ReleaseGateService intact" test -f "$BACK/app/Services/Release/ReleaseGateService.php"
check "release readiness runbook intact" test -f docs/release/release-go-no-go-runbook.md

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
check "sprint14-ci workflow exists" test -f .github/workflows/sprint14-ci.yml
check "sprint14-ci runs sprint14 smoke" hasf "sprint14_smoke.sh" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs production:readiness-check" hasf "production:readiness-check" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs pilot:rc-check" hasf "pilot:rc-check" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs pilot:uat-summary" hasf "pilot:uat-summary" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint14-ci.yml
check "sprint14-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint14-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/onboarding/UAT panel" bash -c \
  '! grep -R "AdminActivity\|OnboardingActivity\|UatActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
