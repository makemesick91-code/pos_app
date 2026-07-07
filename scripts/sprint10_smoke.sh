#!/usr/bin/env bash
#
# Sprint 10 — Subscription & Device Limit Foundation smoke test.
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
TEST=android/app/src/test/java/com/aishtech/poslite
BACK=backend

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
check "PROJECT_RULES lock index lists sprint 10" hasf "sprint-10-subscription-device-limit-foundation.md" docs/PROJECT_RULES.md

echo "== Backend migrations =="
check "subscription_plans migration exists" bash -c 'ls backend/database/migrations/*create_subscription_plans_table.php'
check "tenant_subscriptions migration exists" bash -c 'ls backend/database/migrations/*create_tenant_subscriptions_table.php'
check "registered_devices migration exists" bash -c 'ls backend/database/migrations/*create_registered_devices_table.php'

echo "== Backend models =="
check "SubscriptionPlan model exists" test -f "$BACK/app/Models/SubscriptionPlan.php"
check "TenantSubscription model exists" test -f "$BACK/app/Models/TenantSubscription.php"
check "RegisteredDevice model exists" test -f "$BACK/app/Models/RegisteredDevice.php"

echo "== Backend services & middleware =="
check "SubscriptionStatusService exists" test -f "$BACK/app/Services/Subscriptions/SubscriptionStatusService.php"
check "DeviceRegistrationService exists" test -f "$BACK/app/Services/Subscriptions/DeviceRegistrationService.php"
check "subscription middleware exists" test -f "$BACK/app/Http/Middleware/EnsureTenantSubscriptionIsActive.php"
check "device middleware exists" test -f "$BACK/app/Http/Middleware/EnsureDeviceIsRegistered.php"

echo "== Backend controllers & routes =="
check "subscription status controller exists" test -f "$BACK/app/Http/Controllers/Api/V1/SubscriptionStatusController.php"
check "registered device controller exists" test -f "$BACK/app/Http/Controllers/Api/V1/RegisteredDeviceController.php"
check "device heartbeat controller exists" test -f "$BACK/app/Http/Controllers/Api/V1/DeviceHeartbeatController.php"
check "subscription status route exists" hasf "subscription/status" "$BACK/routes/api.php"
check "device register route exists" hasf "devices/register" "$BACK/routes/api.php"
check "device heartbeat route exists" hasf "devices/heartbeat" "$BACK/routes/api.php"
check "device revoke route exists" hasf "devices/{device}/revoke" "$BACK/routes/api.php"

echo "== Backend tests =="
check "subscription status test exists" test -f "$BACK/tests/Feature/SubscriptionStatusApiTest.php"
check "device registration test exists" test -f "$BACK/tests/Feature/DeviceRegistrationApiTest.php"
check "device limit test exists" test -f "$BACK/tests/Feature/DeviceLimitEnforcementTest.php"
check "subscription middleware test exists" test -f "$BACK/tests/Feature/SubscriptionMiddlewareTest.php"
check "device middleware test exists" test -f "$BACK/tests/Feature/DeviceMiddlewareTest.php"
check "subscription tenant isolation test exists" test -f "$BACK/tests/Feature/SubscriptionTenantIsolationTest.php"

echo "== Android app foundation =="
check "Android Gradle wrapper exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "android gradlew exists" test -f android/gradlew
check "android gradlew executable" test -x android/gradlew
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== Android subscription & device =="
check "DeviceIdentityStore exists" test -f "$APP/core/device/DeviceIdentityStore.kt"
check "DeviceInfoProvider exists" test -f "$APP/core/device/DeviceInfoProvider.kt"
check "DeviceHeaderInterceptor exists" test -f "$APP/core/network/DeviceHeaderInterceptor.kt"
check "SubscriptionDtos exists" test -f "$APP/data/remote/dto/SubscriptionDtos.kt"
check "DeviceDtos exists" test -f "$APP/data/remote/dto/DeviceDtos.kt"
check "SubscriptionRepository exists" test -f "$APP/data/repository/SubscriptionRepository.kt"
check "DeviceRepository exists" test -f "$APP/data/repository/DeviceRepository.kt"
check "SubscriptionStatusActivity exists" test -f "$APP/feature/subscription/SubscriptionStatusActivity.kt"
check "SubscriptionStatusViewModel exists" test -f "$APP/feature/subscription/SubscriptionStatusViewModel.kt"
check "subscription status layout exists" test -f android/app/src/main/res/layout/activity_subscription_status.xml
check "PosApiService has subscription endpoint" hasf "subscription/status" "$APP/core/network/PosApiService.kt"
check "PosApiService has device register endpoint" hasf "devices/register" "$APP/core/network/PosApiService.kt"
check "PosApiService has device heartbeat endpoint" hasf "devices/heartbeat" "$APP/core/network/PosApiService.kt"
check "X-Device-UUID header wired" has "X-Device-UUID" "$APP"
check "device identity store test exists" test -f "$TEST/DeviceIdentityStoreTest.kt"
check "subscription mapping test exists" test -f "$TEST/SubscriptionStatusMappingTest.kt"
check "device header interceptor test exists" test -f "$TEST/DeviceHeaderInterceptorTest.kt"
check "subscription screen strings present" has "Status Langganan" android/app/src/main/res/values/strings.xml

echo "== CI workflow =="
check "sprint10-ci workflow exists" test -f .github/workflows/sprint10-ci.yml
check "sprint10-ci runs smoke" hasf "sprint10_smoke.sh" .github/workflows/sprint10-ci.yml
check "sprint10-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint10-ci.yml
check "sprint10-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint10-ci.yml

echo "== Security: no gateway secrets in Android =="
check "no payment gateway key in Android source" bash -c '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no user password stored in device identity source" bash -c '! grep -Ri "password" android/app/src/main/java/com/aishtech/poslite/core/device'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
