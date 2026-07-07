#!/usr/bin/env bash
#
# Sprint 21 — Public Website / Landing Page Readiness smoke test.
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
SVC="$BACK/app/Services/PublicWebsite"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
PUB_CTRL="$BACK/app/Http/Controllers/PublicWebsite"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
VIEWS="$BACK/resources/views/public-website"
TESTS="$BACK/tests/Feature"
PW=docs/public-website

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 21 evidence exists" test -f docs/sprints/sprint-21-public-website-landing-page-readiness-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 20 Runtime Rule" hasf "Sprint 20 Commercial Launch Readiness & SaaS Packaging Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 21 Runtime Rule" hasf "Sprint 21 Public Website / Landing Page Readiness Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 21" hasf "sprint-21-public-website-landing-page-readiness-foundation.md" docs/PROJECT_RULES.md

echo "== Migrations =="
check "public_website_pages migration exists" bash -c "ls $MIG/*create_public_website_pages_table.php >/dev/null 2>&1"
check "landing_page_versions migration exists" bash -c "ls $MIG/*create_landing_page_versions_table.php >/dev/null 2>&1"
check "lead_interest_submissions migration exists" bash -c "ls $MIG/*create_lead_interest_submissions_table.php >/dev/null 2>&1"
check "public_website_signoffs migration exists" bash -c "ls $MIG/*create_public_website_signoffs_table.php >/dev/null 2>&1"
check "public_website_risks migration exists" bash -c "ls $MIG/*create_public_website_risks_table.php >/dev/null 2>&1"

echo "== Models =="
check "PublicWebsitePage model exists" test -f "$MODELS/PublicWebsitePage.php"
check "LandingPageVersion model exists" test -f "$MODELS/LandingPageVersion.php"
check "LeadInterestSubmission model exists" test -f "$MODELS/LeadInterestSubmission.php"
check "PublicWebsiteSignoff model exists" test -f "$MODELS/PublicWebsiteSignoff.php"
check "PublicWebsiteRisk model exists" test -f "$MODELS/PublicWebsiteRisk.php"
check "PublicWebsitePage status constants present" hasf "STATUS_PUBLISHED" "$MODELS/PublicWebsitePage.php"
check "PublicWebsiteRisk severity constants present" hasf "SEVERITY_CRITICAL" "$MODELS/PublicWebsiteRisk.php"
check "PublicWebsiteSignoff LEGAL_PRIVACY role present" hasf "ROLE_LEGAL_PRIVACY" "$MODELS/PublicWebsiteSignoff.php"

echo "== Services =="
check "PublicWebsiteReadinessService exists" test -f "$SVC/PublicWebsiteReadinessService.php"
check "LandingPageContentService exists" test -f "$SVC/LandingPageContentService.php"
check "LeadInterestGovernanceService exists" test -f "$SVC/LeadInterestGovernanceService.php"
check "SeoMetadataGovernanceService exists" test -f "$SVC/SeoMetadataGovernanceService.php"
check "PrivacyCookieReadinessService exists" test -f "$SVC/PrivacyCookieReadinessService.php"
check "PublicWebsiteRiskGovernanceService exists" test -f "$SVC/PublicWebsiteRiskGovernanceService.php"
check "PublicWebsiteGoNoGoService exists" test -f "$SVC/PublicWebsiteGoNoGoService.php"

echo "== Config =="
check "public_website config exists" test -f "$BACK/config/public_website.php"
check "pos_foundation lists sprint_21" hasf "sprint_21" "$BACK/config/pos_foundation.php"
check "pos_foundation has public website rule" hasf "public_website_readiness_required" "$BACK/config/pos_foundation.php"
check "pos_foundation has no self-service signup rule" hasf "no_public_self_service_signup_sprint_21" "$BACK/config/pos_foundation.php"

echo "== Public routes/controllers/views =="
check "LandingPageController exists" test -f "$PUB_CTRL/LandingPageController.php"
check "PackagePageController exists" test -f "$PUB_CTRL/PackagePageController.php"
check "PrivacyPageController exists" test -f "$PUB_CTRL/PrivacyPageController.php"
check "TermsPageController exists" test -f "$PUB_CTRL/TermsPageController.php"
check "LeadInterestController exists" test -f "$PUB_CTRL/LeadInterestController.php"
check "StoreLeadInterestRequest exists" test -f "$BACK/app/Http/Requests/PublicWebsite/StoreLeadInterestRequest.php"
check "layout view exists" test -f "$VIEWS/layout.blade.php"
check "home view exists" test -f "$VIEWS/home.blade.php"
check "packages view exists" test -f "$VIEWS/packages.blade.php"
check "privacy view exists" test -f "$VIEWS/privacy.blade.php"
check "terms view exists" test -f "$VIEWS/terms.blade.php"
check "thank-you view exists" test -f "$VIEWS/thank-you.blade.php"
check "web routes register /interest" hasf "/interest" "$BACK/routes/web.php"
check "web routes register /packages" hasf "/packages" "$BACK/routes/web.php"
check "interest route rate-limited" hasf "throttle" "$BACK/routes/web.php"

echo "== Admin public website APIs =="
check "PublicWebsitePageController exists" test -f "$ADMIN_CTRL/PublicWebsitePageController.php"
check "LandingPageVersionController exists" test -f "$ADMIN_CTRL/LandingPageVersionController.php"
check "LeadInterestSubmissionController exists" test -f "$ADMIN_CTRL/LeadInterestSubmissionController.php"
check "PublicWebsiteRiskController exists" test -f "$ADMIN_CTRL/PublicWebsiteRiskController.php"
check "PublicWebsiteSignoffController exists" test -f "$ADMIN_CTRL/PublicWebsiteSignoffController.php"
check "PublicWebsiteReadinessController exists" test -f "$ADMIN_CTRL/PublicWebsiteReadinessController.php"
check "PublicWebsiteContentSummaryController exists" test -f "$ADMIN_CTRL/PublicWebsiteContentSummaryController.php"
check "PublicWebsiteLeadSummaryController exists" test -f "$ADMIN_CTRL/PublicWebsiteLeadSummaryController.php"
check "PublicWebsiteGoNoGoController exists" test -f "$ADMIN_CTRL/PublicWebsiteGoNoGoController.php"
check "StorePublicWebsitePageRequest exists" test -f "$ADMIN_REQ/StorePublicWebsitePageRequest.php"
check "StoreLandingPageVersionRequest exists" test -f "$ADMIN_REQ/StoreLandingPageVersionRequest.php"
check "AcceptPublicWebsiteRiskRequest exists" test -f "$ADMIN_REQ/AcceptPublicWebsiteRiskRequest.php"
check "StorePublicWebsiteSignoffRequest exists" test -f "$ADMIN_REQ/StorePublicWebsiteSignoffRequest.php"
check "PublicWebsiteGoNoGoResource exists" test -f "$ADMIN_RES/PublicWebsiteGoNoGoResource.php"
check "routes register public-website-pages" hasf "public-website-pages" "$BACK/routes/api.php"
check "routes register landing-page-versions" hasf "landing-page-versions" "$BACK/routes/api.php"
check "routes register lead-interest-submissions" hasf "lead-interest-submissions" "$BACK/routes/api.php"
check "routes register public-website-go-no-go" hasf "public-website-go-no-go" "$BACK/routes/api.php"
check "public website behind platform.admin group" hasf "platform.admin" "$BACK/routes/api.php"

echo "== Commands =="
check "public-website:readiness command exists" test -f "$CMD/PublicWebsiteReadinessCommand.php"
check "public-website:content-summary command exists" test -f "$CMD/PublicWebsiteContentSummaryCommand.php"
check "public-website:lead-summary command exists" test -f "$CMD/PublicWebsiteLeadSummaryCommand.php"
check "public-website:go-no-go command exists" test -f "$CMD/PublicWebsiteGoNoGoCommand.php"
check "readiness supports --json" hasf "json" "$CMD/PublicWebsiteReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/PublicWebsiteGoNoGoCommand.php"

echo "== Public website docs =="
check "landing-page-content-map exists" test -f "$PW/landing-page-content-map.md"
check "public-website-content-governance exists" test -f "$PW/public-website-content-governance.md"
check "seo-metadata-readiness exists" test -f "$PW/seo-metadata-readiness.md"
check "privacy-cookie-readiness exists" test -f "$PW/privacy-cookie-readiness.md"
check "lead-interest-policy exists" test -f "$PW/lead-interest-policy.md"
check "package-pricing-content-alignment exists" test -f "$PW/package-pricing-content-alignment.md"
check "public-website-qa-checklist exists" test -f "$PW/public-website-qa-checklist.md"
check "public-website-risk-register exists" test -f "$PW/public-website-risk-register.md"
check "public-website-go-watch-no-go-report exists" test -f "$PW/public-website-go-watch-no-go-report.md"

echo "== Tests =="
check "LandingPageContentServiceTest exists" test -f "$TESTS/LandingPageContentServiceTest.php"
check "LeadInterestGovernanceServiceTest exists" test -f "$TESTS/LeadInterestGovernanceServiceTest.php"
check "PublicWebsiteRiskGovernanceServiceTest exists" test -f "$TESTS/PublicWebsiteRiskGovernanceServiceTest.php"
check "PublicWebsiteReadinessServiceTest exists" test -f "$TESTS/PublicWebsiteReadinessServiceTest.php"
check "PublicWebsiteAdminApiTest exists" test -f "$TESTS/PublicWebsiteAdminApiTest.php"
check "PublicWebsitePublicRouteTest exists" test -f "$TESTS/PublicWebsitePublicRouteTest.php"
check "PublicWebsiteCommandsTest exists" test -f "$TESTS/PublicWebsiteCommandsTest.php"
check "PublicWebsiteSecurityScanTest exists" test -f "$TESTS/PublicWebsiteSecurityScanTest.php"
check "PublicWebsiteRegressionRouteTest exists" test -f "$TESTS/PublicWebsiteRegressionRouteTest.php"

echo "== Sprint 13-20 foundation intact =="
check "release:go-no-go command intact" hasf "release:go-no-go" "$CMD/ReleaseGoNoGoCommand.php"
check "pilot:closure-check command intact" hasf "pilot:closure-check" "$CMD/PilotClosureCheckCommand.php"
check "production:post-handover-go-no-go command intact" hasf "production:post-handover-go-no-go" "$CMD/ProductionPostHandoverGoNoGoCommand.php"
check "commercial:launch-go-no-go command intact" hasf "commercial:launch-go-no-go" "$CMD/CommercialLaunchGoNoGoCommand.php"

echo "== Android release readiness =="
check "android_release_readiness.sh exists" test -f scripts/android_release_readiness.sh
check "android_release_readiness.sh executable" test -x scripts/android_release_readiness.sh
check "Android Gradle wrapper jar exists" test -f android/gradle/wrapper/gradle-wrapper.jar
check "Android package remains com.aishtech.poslite" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "minSdk 26" hasf "minSdk = 26" android/app/build.gradle.kts
check "targetSdk 35" hasf "targetSdk = 35" android/app/build.gradle.kts

echo "== CI workflow =="
check "sprint21-ci workflow exists" test -f .github/workflows/sprint21-ci.yml
check "sprint21-ci runs sprint21 smoke" hasf "sprint21_smoke.sh" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs commercial:launch-go-no-go" hasf "commercial:launch-go-no-go" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs public-website:readiness" hasf "public-website:readiness" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs public-website:go-no-go" hasf "public-website:go-no-go" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint21-ci.yml
check "sprint21-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint21-ci.yml

echo "== Security: no secrets / no signup in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|QRIS_FAKE_WEBHOOK_SECRET" android/app/src/main/java android/app/src/main/res'
check "no Android admin/website/signup panel" bash -c \
  '! grep -R "AdminActivity\|SignupActivity\|LandingActivity\|WebsiteActivity\|LeadActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
