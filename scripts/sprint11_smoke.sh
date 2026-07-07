#!/usr/bin/env bash
#
# Sprint 11 — Admin SaaS Control Panel Foundation smoke test.
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

# Custom grep helpers (silent).
has() { grep -Rq "$1" "$2"; }
hasf() { grep -q "$1" "$2"; }

APP=android/app/src/main/java/com/aishtech/poslite
BACK=backend
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
ADMIN_SVC="$BACK/app/Services/Admin"

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
check "PROJECT_RULES lock index lists sprint 11" hasf "sprint-11-admin-saas-control-panel-foundation.md" docs/PROJECT_RULES.md

echo "== Platform admin & audit migrations =="
check "platform admin migration exists" bash -c 'ls backend/database/migrations/*add_platform_admin_fields_to_users_table.php'
check "admin_audit_logs migration exists" bash -c 'ls backend/database/migrations/*create_admin_audit_logs_table.php'
check "User has isPlatformAdmin helper" hasf "isPlatformAdmin" "$BACK/app/Models/User.php"
check "User factory has platformAdmin state" hasf "platformAdmin" "$BACK/database/factories/UserFactory.php"

echo "== Backend admin models & middleware =="
check "AdminAuditLog model exists" test -f "$BACK/app/Models/AdminAuditLog.php"
check "AdminAuditLogFactory exists" test -f "$BACK/database/factories/AdminAuditLogFactory.php"
check "EnsurePlatformAdmin middleware exists" test -f "$BACK/app/Http/Middleware/EnsurePlatformAdmin.php"
check "platform.admin alias registered" hasf "platform.admin" "$BACK/bootstrap/app.php"

echo "== Backend admin services =="
check "AdminAuditLogger exists" test -f "$ADMIN_SVC/AdminAuditLogger.php"
check "AdminTenantService exists" test -f "$ADMIN_SVC/AdminTenantService.php"
check "AdminSubscriptionService exists" test -f "$ADMIN_SVC/AdminSubscriptionService.php"
check "AdminDeviceService exists" test -f "$ADMIN_SVC/AdminDeviceService.php"
check "AdminPlanService exists" test -f "$ADMIN_SVC/AdminPlanService.php"

echo "== Backend admin controllers =="
check "AdminTenantController exists" test -f "$ADMIN_CTRL/AdminTenantController.php"
check "AdminTenantSubscriptionController exists" test -f "$ADMIN_CTRL/AdminTenantSubscriptionController.php"
check "AdminTenantDeviceController exists" test -f "$ADMIN_CTRL/AdminTenantDeviceController.php"
check "AdminSubscriptionPlanController exists" test -f "$ADMIN_CTRL/AdminSubscriptionPlanController.php"
check "AdminAuditLogController exists" test -f "$ADMIN_CTRL/AdminAuditLogController.php"

echo "== Backend admin requests =="
check "IndexAdminTenantRequest exists" test -f "$ADMIN_REQ/IndexAdminTenantRequest.php"
check "StoreAdminTenantSubscriptionRequest exists" test -f "$ADMIN_REQ/StoreAdminTenantSubscriptionRequest.php"
check "UpdateAdminTenantSubscriptionRequest exists" test -f "$ADMIN_REQ/UpdateAdminTenantSubscriptionRequest.php"
check "IndexAdminDeviceRequest exists" test -f "$ADMIN_REQ/IndexAdminDeviceRequest.php"
check "StoreAdminSubscriptionPlanRequest exists" test -f "$ADMIN_REQ/StoreAdminSubscriptionPlanRequest.php"
check "UpdateAdminSubscriptionPlanRequest exists" test -f "$ADMIN_REQ/UpdateAdminSubscriptionPlanRequest.php"
check "IndexAdminAuditLogRequest exists" test -f "$ADMIN_REQ/IndexAdminAuditLogRequest.php"

echo "== Backend admin resources =="
check "AdminTenantResource exists" test -f "$ADMIN_RES/AdminTenantResource.php"
check "AdminTenantDetailResource exists" test -f "$ADMIN_RES/AdminTenantDetailResource.php"
check "AdminTenantSubscriptionResource exists" test -f "$ADMIN_RES/AdminTenantSubscriptionResource.php"
check "AdminDeviceResource exists" test -f "$ADMIN_RES/AdminDeviceResource.php"
check "AdminSubscriptionPlanResource exists" test -f "$ADMIN_RES/AdminSubscriptionPlanResource.php"
check "AdminAuditLogResource exists" test -f "$ADMIN_RES/AdminAuditLogResource.php"

echo "== Backend admin routes =="
check "admin route group prefix exists" hasf "prefix('admin')" "$BACK/routes/api.php"
check "admin tenant routes wired" hasf "AdminTenantController" "$BACK/routes/api.php"
check "admin subscription routes wired" hasf "AdminTenantSubscriptionController" "$BACK/routes/api.php"
check "admin device revoke route exists" hasf "devices/{device}/revoke" "$BACK/routes/api.php"
check "admin plan routes exist" hasf "subscription-plans" "$BACK/routes/api.php"
check "admin audit log routes exist" hasf "audit-logs" "$BACK/routes/api.php"
check "admin route group uses platform.admin" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Backend admin tests =="
check "admin authorization test exists" test -f "$BACK/tests/Feature/AdminPlatformAuthorizationTest.php"
check "admin tenant test exists" test -f "$BACK/tests/Feature/AdminTenantApiTest.php"
check "admin subscription management test exists" test -f "$BACK/tests/Feature/AdminSubscriptionManagementTest.php"
check "admin device management test exists" test -f "$BACK/tests/Feature/AdminDeviceManagementTest.php"
check "admin plan management test exists" test -f "$BACK/tests/Feature/AdminSubscriptionPlanManagementTest.php"
check "admin audit log test exists" test -f "$BACK/tests/Feature/AdminAuditLogTest.php"
check "admin no-regression business test exists" test -f "$BACK/tests/Feature/AdminNoRegressionBusinessApiTest.php"

echo "== No Android admin panel =="
check "no AdminActivity in Android source" bash -c '! grep -Rl "AdminActivity\|AdminTenant\|AdminSubscriptionPlan\|AdminAuditLog" android/app/src/main/java android/app/src/main/res 2>/dev/null | grep .'
check "Android Gradle wrapper exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "android gradlew executable" test -x android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint11-ci workflow exists" test -f .github/workflows/sprint11-ci.yml
check "sprint11-ci runs smoke" hasf "sprint11_smoke.sh" .github/workflows/sprint11-ci.yml
check "sprint11-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint11-ci.yml
check "sprint11-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint11-ci.yml

echo "== Security: no secrets =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no admin secret hardcoded in Android" bash -c '! grep -Ri "ADMIN_SECRET\|PLATFORM_ADMIN_TOKEN" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
