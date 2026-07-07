#!/usr/bin/env bash
#
# Sprint 13 — Production Readiness & Release Hardening Foundation smoke test.
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
REL_SVC="$BACK/app/Services/Release"
CMD="$BACK/app/Console/Commands"

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 13 evidence exists" test -f docs/sprints/sprint-13-production-readiness-release-hardening-foundation.md

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
check "PROJECT_RULES lock index lists sprint 13" hasf "sprint-13-production-readiness-release-hardening-foundation.md" docs/PROJECT_RULES.md

echo "== Release services & commands =="
check "ProductionReadinessService exists" test -f "$REL_SVC/ProductionReadinessService.php"
check "ReleaseGateService exists" test -f "$REL_SVC/ReleaseGateService.php"
check "BackupReadinessService exists" test -f "$REL_SVC/BackupReadinessService.php"
check "production:readiness-check command exists" hasf "production:readiness-check" "$CMD/ProductionReadinessCheckCommand.php"
check "release:go-no-go command exists" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "release_readiness config exists" test -f "$BACK/config/release_readiness.php"

echo "== Release docs =="
check "production readiness checklist exists" test -f docs/release/production-readiness-checklist.md
check "backup/restore runbook exists" test -f docs/release/backup-restore-runbook.md
check "release go/no-go runbook exists" test -f docs/release/release-go-no-go-runbook.md

echo "== Config lock =="
check "pos_foundation lists sprint_13" hasf "sprint_13" "$BACK/config/pos_foundation.php"
check "pos_foundation release_readiness flag" hasf "release_readiness_gate_required" "$BACK/config/pos_foundation.php"
check "pos_foundation backup runbook flag" hasf "backup_restore_runbook_required" "$BACK/config/pos_foundation.php"
check "pos_foundation android release flag" hasf "android_release_readiness_required" "$BACK/config/pos_foundation.php"

echo "== Release tests =="
check "ProductionReadinessCommandTest exists" test -f "$BACK/tests/Feature/ProductionReadinessCommandTest.php"
check "ReleaseGoNoGoCommandTest exists" test -f "$BACK/tests/Feature/ReleaseGoNoGoCommandTest.php"
check "ProductionReadinessServiceTest exists" test -f "$BACK/tests/Feature/ProductionReadinessServiceTest.php"
check "ReleaseGateServiceTest exists" test -f "$BACK/tests/Feature/ReleaseGateServiceTest.php"
check "ReleaseRegressionRouteTest exists" test -f "$BACK/tests/Feature/ReleaseRegressionRouteTest.php"
check "ReleaseSecurityScanTest exists" test -f "$BACK/tests/Feature/ReleaseSecurityScanTest.php"

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
check "sprint13-ci workflow exists" test -f .github/workflows/sprint13-ci.yml
check "sprint13-ci runs sprint13 smoke" hasf "sprint13_smoke.sh" .github/workflows/sprint13-ci.yml
check "sprint13-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint13-ci.yml
check "sprint13-ci runs production:readiness-check" hasf "production:readiness-check" .github/workflows/sprint13-ci.yml
check "sprint13-ci runs release:go-no-go" hasf "release:go-no-go" .github/workflows/sprint13-ci.yml
check "sprint13-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint13-ci.yml
check "sprint13-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint13-ci.yml

echo "== Security: no secrets in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
