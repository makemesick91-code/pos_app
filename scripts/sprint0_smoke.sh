#!/usr/bin/env bash
#
# Sprint 0 smoke validation.
# Verifies the project setup structure without requiring PHP, Gradle, or an
# Android SDK. Run from the repository root:
#
#   bash scripts/sprint0_smoke.sh
#
set -euo pipefail

# Resolve repo root (parent of this script's directory).
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

pass=0
fail=0

check() {
    local label="$1"
    shift
    if "$@" >/dev/null 2>&1; then
        printf '  [PASS] %s\n' "$label"
        pass=$((pass + 1))
    else
        printf '  [FAIL] %s\n' "$label"
        fail=$((fail + 1))
    fi
}

echo "== Sprint 0 smoke validation =="
echo ""

echo "-- Foundation & docs --"
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
check "PROJECT_RULES exists" test -f docs/PROJECT_RULES.md
check "README references foundation" grep -q "POS_ANDROID_SAAS_FOUNDATION" README.md
check "PROJECT_RULES references foundation" grep -q "POS_ANDROID_SAAS_FOUNDATION" docs/PROJECT_RULES.md
check "sprint-0 evidence doc exists" test -f docs/sprints/sprint-0-project-setup.md

echo ""
echo "-- Backend --"
check "backend folder exists" test -d backend
check "backend composer.json exists" test -f backend/composer.json
check "backend api routes exist" test -f backend/routes/api.php
check "backend health route defined" grep -q "'/health'" backend/routes/api.php
check "backend health returns app name" grep -q "Aish POS Lite API" backend/routes/api.php

echo ""
echo "-- Android --"
check "android folder exists" test -d android
check "android settings.gradle.kts exists" test -f android/settings.gradle.kts
check "android app build file exists" test -f android/app/build.gradle.kts
check "android manifest exists" test -f android/app/src/main/AndroidManifest.xml
check "android package present" grep -rq "com.aishtech.poslite" android/app/build.gradle.kts
check "android minSdk = 26" grep -Eq "minSdk *= *26" android/app/build.gradle.kts
check "android targetSdk = 35" grep -Eq "targetSdk *= *35" android/app/build.gradle.kts

echo ""
echo "-- Hygiene (nothing forbidden committed) --"
if command -v git >/dev/null 2>&1 && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
    check "no APK/AAB committed" bash -c '! git ls-files | grep -qE "\.(apk|aab)$"'
    check "no vendor/ committed" bash -c '! git ls-files | grep -qE "(^|/)vendor/"'
    check "no node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)node_modules/"'
else
    echo "  [SKIP] git not available — hygiene checks skipped"
fi

echo ""
echo "== Result: ${pass} passed, ${fail} failed =="

if [ "$fail" -ne 0 ]; then
    exit 1
fi
