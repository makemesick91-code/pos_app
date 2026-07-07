#!/usr/bin/env bash
#
# Sprint 12 — Tenant Onboarding & Demo Data Foundation smoke test.
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

has() { grep -Rq "$1" "$2"; }
hasf() { grep -q "$1" "$2"; }

BACK=backend
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
ONB_SVC="$BACK/app/Services/Onboarding"

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "sprint 0 evidence exists" test -f docs/sprints/sprint-0-project-setup.md
check "sprint 1 evidence exists" test -f docs/sprints/sprint-1-saas-tenant-foundation.md
check "sprint 2 evidence exists" test -f docs/sprints/sprint-2-product-foundation.md
check "sprint 3 evidence exists" test -f docs/sprints/sprint-3-android-cashier-foundation.md
check "sprint 4 evidence exists" test -f docs/sprints/sprint-4-sales-backend-integration.md
check "sprint 5 evidence exists" test -f docs/sprints/sprint-5-qris-payment-gateway-foundation.md
check "sprint 6 evidence exists" test -f docs/sprints/sprint-6-printer-receipt-foundation.md
check "sprint 7 evidence exists" test -f docs/sprints/sprint-7-offline-cash-sync-foundation.md
check "sprint 8 evidence exists" test -f docs/sprints/sprint-8-inventory-simple-foundation.md
check "sprint 9 evidence exists" test -f docs/sprints/sprint-9-reports-closing-foundation.md
check "sprint 10 evidence exists" test -f docs/sprints/sprint-10-subscription-device-limit-foundation.md
check "sprint 11 evidence exists" test -f docs/sprints/sprint-11-admin-saas-control-panel-foundation.md
check "sprint 12 evidence exists" test -f docs/sprints/sprint-12-tenant-onboarding-demo-data-foundation.md

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
check "PROJECT_RULES lock index lists sprint 12" hasf "sprint-12-tenant-onboarding-demo-data-foundation.md" docs/PROJECT_RULES.md

echo "== Onboarding migration, model, factory =="
check "tenant_onboarding_runs migration exists" bash -c 'ls backend/database/migrations/*create_tenant_onboarding_runs_table.php'
check "TenantOnboardingRun model exists" test -f "$BACK/app/Models/TenantOnboardingRun.php"
check "TenantOnboardingRunFactory exists" test -f "$BACK/database/factories/TenantOnboardingRunFactory.php"
check "onboarding reference is unique" hasf "onboarding_reference')->unique" "$(ls backend/database/migrations/*create_tenant_onboarding_runs_table.php)"

echo "== Onboarding services =="
check "TenantOnboardingService exists" test -f "$ONB_SVC/TenantOnboardingService.php"
check "TenantOnboardingChecklistService exists" test -f "$ONB_SVC/TenantOnboardingChecklistService.php"
check "DemoDataSeederService exists" test -f "$ONB_SVC/DemoDataSeederService.php"
check "DemoDataResetService exists" test -f "$ONB_SVC/DemoDataResetService.php"
check "DemoCatalogFactory exists" test -f "$ONB_SVC/DemoCatalogFactory.php"
check "seeder uses OPENING inventory movement" hasf "createOpeningMovement" "$ONB_SVC/DemoDataSeederService.php"
check "reset guarded by manifest ownership" hasf "ownedQuery" "$ONB_SVC/DemoDataResetService.php"

echo "== Onboarding controllers, requests, resources =="
check "TenantOnboardingController exists" test -f "$ADMIN_CTRL/TenantOnboardingController.php"
check "TenantOnboardingStatusController exists" test -f "$ADMIN_CTRL/TenantOnboardingStatusController.php"
check "TenantDemoDataController exists" test -f "$ADMIN_CTRL/TenantDemoDataController.php"
check "StoreTenantOnboardingRequest exists" test -f "$ADMIN_REQ/StoreTenantOnboardingRequest.php"
check "IndexTenantOnboardingRequest exists" test -f "$ADMIN_REQ/IndexTenantOnboardingRequest.php"
check "SeedTenantDemoDataRequest exists" test -f "$ADMIN_REQ/SeedTenantDemoDataRequest.php"
check "ResetTenantDemoDataRequest exists" test -f "$ADMIN_REQ/ResetTenantDemoDataRequest.php"
check "TenantOnboardingRunResource exists" test -f "$ADMIN_RES/TenantOnboardingRunResource.php"
check "TenantOnboardingStatusResource exists" test -f "$ADMIN_RES/TenantOnboardingStatusResource.php"
check "TenantDemoDataResource exists" test -f "$ADMIN_RES/TenantDemoDataResource.php"

echo "== Onboarding routes =="
check "onboarding create route exists" hasf "tenant-onboarding" "$BACK/routes/api.php"
check "onboarding controller wired" hasf "TenantOnboardingController" "$BACK/routes/api.php"
check "onboarding status route exists" hasf "onboarding-status" "$BACK/routes/api.php"
check "demo data route exists" hasf "demo-data" "$BACK/routes/api.php"
check "demo data reset route exists" hasf "demo-data/reset" "$BACK/routes/api.php"
check "onboarding routes under platform.admin" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Onboarding tests =="
check "onboarding api test exists" test -f "$BACK/tests/Feature/TenantOnboardingApiTest.php"
check "onboarding idempotency test exists" test -f "$BACK/tests/Feature/TenantOnboardingIdempotencyTest.php"
check "demo data api test exists" test -f "$BACK/tests/Feature/TenantDemoDataApiTest.php"
check "demo data reset test exists" test -f "$BACK/tests/Feature/TenantDemoDataResetTest.php"
check "onboarding audit log test exists" test -f "$BACK/tests/Feature/TenantOnboardingAuditLogTest.php"
check "onboarding authorization test exists" test -f "$BACK/tests/Feature/TenantOnboardingAuthorizationTest.php"
check "onboarding regression test exists" test -f "$BACK/tests/Feature/TenantOnboardingRegressionTest.php"

echo "== Config lock =="
check "pos_foundation lists sprint_12" hasf "sprint_12" "$BACK/config/pos_foundation.php"
check "pos_foundation onboarding rule flag" hasf "tenant_onboarding_platform_admin_only" "$BACK/config/pos_foundation.php"

echo "== No Android admin/onboarding panel =="
check "no AdminActivity in Android source" bash -c '! grep -Rl "AdminActivity\|OnboardingActivity\|TenantOnboarding" android/app/src/main/java android/app/src/main/res 2>/dev/null | grep .'
check "Android Gradle wrapper exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "android gradlew executable" test -x android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint12-ci workflow exists" test -f .github/workflows/sprint12-ci.yml
check "sprint12-ci runs smoke" hasf "sprint12_smoke.sh" .github/workflows/sprint12-ci.yml
check "sprint12-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint12-ci.yml
check "sprint12-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint12-ci.yml

echo "== Security: no secrets / no password leak =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no admin secret hardcoded in Android" bash -c '! grep -Ri "ADMIN_SECRET\|PLATFORM_ADMIN_TOKEN" android/app/src/main/java android/app/src/main/res'
check "onboarding resource does not expose owner_password" bash -c '! grep -q "owner_password" backend/app/Http/Resources/Api/V1/Admin/TenantOnboardingRunResource.php'
check "onboarding service does not store password in metadata" bash -c '! grep -iE "metadata.*owner_password|owner_password.*metadata" backend/app/Services/Onboarding/TenantOnboardingService.php'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
