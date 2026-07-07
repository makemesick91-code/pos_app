#!/usr/bin/env bash
#
# Sprint 13 — Android release readiness (static validation only).
# Does NOT require signing keys and does NOT build the app; CI owns the
# assembleDebug / testDebugUnitTest build gate.
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

GRADLE="android/app/build.gradle.kts"

echo "== Gradle wrapper =="
check "android/gradlew exists" test -f android/gradlew
check "android/gradlew is executable" test -x android/gradlew
check "gradlew.bat exists" test -f android/gradlew.bat
check "gradle-wrapper.jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "gradle-wrapper.properties exists" test -f android/gradle/wrapper/gradle-wrapper.properties

echo "== Package & SDK governance =="
check "package remains com.aishtech.poslite" grep -q "com.aishtech.poslite" "$GRADLE"
check "minSdk = 26" grep -q "minSdk = 26" "$GRADLE"
check "targetSdk = 35" grep -q "targetSdk = 35" "$GRADLE"

echo "== Version governance =="
check "versionCode present" grep -q "versionCode" "$GRADLE"
check "versionName present" grep -q "versionName" "$GRADLE"

echo "== No admin/onboarding panel in Android =="
check "no AdminActivity/OnboardingActivity/TenantOnboarding" bash -c \
  '! grep -Rl "AdminActivity\|OnboardingActivity\|TenantOnboarding" android/app/src/main/java android/app/src/main/res 2>/dev/null | grep .'

echo "== No secrets in Android source =="
check "no payment gateway secret" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no hardcoded production API secret" bash -c \
  '! grep -Ri "PROD_API_SECRET\|PRODUCTION_API_KEY\|PLATFORM_ADMIN_TOKEN" android/app/src/main/java android/app/src/main/res'
check "no committed keystore" bash -c \
  '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo "== No committed build artifacts =="
check "no committed APK/AAB" bash -c \
  '! git ls-files | grep -qE "\.apk$|\.aab$"'
check "no committed app/build or .gradle" bash -c \
  '! git ls-files | grep -qE "(^|/)app/build/|(^|/)\.gradle/"'

echo ""
echo "Android release readiness — Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
