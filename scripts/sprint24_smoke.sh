#!/usr/bin/env bash
#
# Sprint 24 — Subscription Renewal & Dunning Governance Foundation smoke test.
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
SVC="$BACK/app/Services/SubscriptionRenewal"
CMD="$BACK/app/Console/Commands"
ADMIN_CTRL="$BACK/app/Http/Controllers/Api/V1/Admin"
ADMIN_REQ="$BACK/app/Http/Requests/Api/V1/Admin"
ADMIN_RES="$BACK/app/Http/Resources/Api/V1/Admin"
MIG="$BACK/database/migrations"
MODELS="$BACK/app/Models"
TESTS="$BACK/tests/Feature"
SR=docs/subscription-renewal

echo "== Documentation & foundation =="
check "foundation document exists" test -f docs/foundation/POS_ANDROID_SAAS_FOUNDATION.md
for n in 0 1 2 3 4 5 6 7 8 9 10 11 12 13 14 15 16 17 18 19 20 21 22 23; do
  check "sprint $n evidence exists" bash -c "ls docs/sprints/sprint-$n-*.md >/dev/null 2>&1"
done
check "sprint 24 evidence exists" test -f docs/sprints/sprint-24-subscription-renewal-dunning-governance-foundation.md

echo "== Application rules lock =="
check "PROJECT_RULES has Foundation Lock Index" hasf "Foundation Lock Index" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 0 Runtime Rule" hasf "Sprint 0 Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 23 Runtime Rule" hasf "Sprint 23 Billing Collection Governance Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES has Sprint 24 Runtime Rule" hasf "Sprint 24 Subscription Renewal & Dunning Governance Foundation Runtime Rule" docs/PROJECT_RULES.md
check "PROJECT_RULES lock index lists sprint 24" hasf "sprint-24-subscription-renewal-dunning-governance-foundation.md" docs/PROJECT_RULES.md

echo "== README =="
check "README has Sprint 24 section" hasf "Sprint 24 — Subscription Renewal & Dunning Governance Foundation" README.md

echo "== Migrations =="
for t in subscription_renewal_policies subscription_renewal_runs subscription_renewal_candidates subscription_dunning_notices subscription_renewal_decisions subscription_renewal_activities subscription_renewal_risks subscription_renewal_signoffs; do
  check "$t migration exists" bash -c "ls $MIG/*create_${t}_table.php >/dev/null 2>&1"
done

echo "== Models =="
for m in SubscriptionRenewalPolicy SubscriptionRenewalRun SubscriptionRenewalCandidate SubscriptionDunningNotice SubscriptionRenewalDecision SubscriptionRenewalActivity SubscriptionRenewalRisk SubscriptionRenewalSignoff; do
  check "$m model exists" test -f "$MODELS/$m.php"
done

echo "== Config =="
check "subscription_renewal config exists" test -f "$BACK/config/subscription_renewal.php"
check "pos_foundation lists sprint_24" hasf "sprint_24" "$BACK/config/pos_foundation.php"
check "pos_foundation has renewal rule" hasf "subscription_renewal_governance_required" "$BACK/config/pos_foundation.php"
check "pos_foundation has no auto charge rule" hasf "no_auto_charge_sprint_24" "$BACK/config/pos_foundation.php"
check "pos_foundation has no auto renewal rule" hasf "no_auto_subscription_renewal_sprint_24" "$BACK/config/pos_foundation.php"

echo "== Services =="
for s in SubscriptionRenewalPolicyService SubscriptionRenewalRunService SubscriptionRenewalCandidateService SubscriptionDunningNoticeService SubscriptionRenewalDecisionService SubscriptionRenewalActivityService SubscriptionRenewalRiskGovernanceService SubscriptionRenewalReadinessService SubscriptionRenewalGoNoGoService SanitizesSubscriptionRenewalText; do
  check "$s exists" test -f "$SVC/$s.php"
done

echo "== Controllers / requests / resources =="
check "PolicyController exists" test -f "$ADMIN_CTRL/SubscriptionRenewalPolicyController.php"
check "RunController exists" test -f "$ADMIN_CTRL/SubscriptionRenewalRunController.php"
check "CandidateController exists" test -f "$ADMIN_CTRL/SubscriptionRenewalCandidateController.php"
check "DunningNoticeController exists" test -f "$ADMIN_CTRL/SubscriptionDunningNoticeController.php"
check "DecisionController exists" test -f "$ADMIN_CTRL/SubscriptionRenewalDecisionController.php"
check "GoNoGoController exists" test -f "$ADMIN_CTRL/SubscriptionRenewalGoNoGoController.php"
check "ApplyManualRenewalDecisionRequest exists" test -f "$ADMIN_REQ/ApplyManualRenewalDecisionRequest.php"
check "StoreSubscriptionDunningNoticeRequest exists" test -f "$ADMIN_REQ/StoreSubscriptionDunningNoticeRequest.php"
check "SubscriptionRenewalCandidateResource exists" test -f "$ADMIN_RES/SubscriptionRenewalCandidateResource.php"
check "SubscriptionRenewalGoNoGoResource exists" test -f "$ADMIN_RES/SubscriptionRenewalGoNoGoResource.php"

echo "== Commands =="
check "readiness command exists" test -f "$CMD/SubscriptionRenewalReadinessCommand.php"
check "candidate-summary command exists" test -f "$CMD/SubscriptionRenewalCandidateSummaryCommand.php"
check "dunning-summary command exists" test -f "$CMD/SubscriptionDunningSummaryCommand.php"
check "go-no-go command exists" test -f "$CMD/SubscriptionRenewalGoNoGoCommand.php"
check "readiness supports --json" hasf "json" "$CMD/SubscriptionRenewalReadinessCommand.php"
check "go-no-go supports --strict" hasf "strict" "$CMD/SubscriptionRenewalGoNoGoCommand.php"

echo "== Routes =="
check "routes register policies" hasf "subscription-renewal/policies" "$BACK/routes/api.php"
check "routes register apply-manual-renewal" hasf "apply-manual-renewal" "$BACK/routes/api.php"
check "routes register go-no-go" hasf "subscription-renewal/go-no-go" "$BACK/routes/api.php"

echo "== Subscription renewal docs =="
check "subscription-renewal-policy doc exists" test -f "$SR/subscription-renewal-policy.md"
check "dunning-manual-notice-policy doc exists" test -f "$SR/dunning-manual-notice-policy.md"
check "renewal-lifecycle-map doc exists" test -f "$SR/renewal-lifecycle-map.md"
check "grace-overdue-governance doc exists" test -f "$SR/grace-overdue-governance.md"
check "manual-renewal-decision-playbook doc exists" test -f "$SR/manual-renewal-decision-playbook.md"
check "subscription-renewal-risk-register doc exists" test -f "$SR/subscription-renewal-risk-register.md"
check "subscription-renewal-go-watch-no-go-report doc exists" test -f "$SR/subscription-renewal-go-watch-no-go-report.md"

echo "== Tests =="
for t in SubscriptionRenewalPolicyServiceTest SubscriptionRenewalRunServiceTest SubscriptionRenewalCandidateServiceTest SubscriptionDunningNoticeServiceTest SubscriptionRenewalDecisionServiceTest SubscriptionRenewalActivityServiceTest SubscriptionRenewalRiskGovernanceServiceTest SubscriptionRenewalReadinessServiceTest SubscriptionRenewalGoNoGoServiceTest SubscriptionRenewalAdminApiTest SubscriptionRenewalCommandsTest SubscriptionRenewalSecurityScanTest SubscriptionRenewalRegressionRouteTest; do
  check "$t exists" test -f "$TESTS/$t.php"
done

echo "== CI workflow =="
check "sprint24-ci workflow exists" test -f .github/workflows/sprint24-ci.yml
check "sprint24-ci runs sprint24 smoke" hasf "sprint24_smoke.sh" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs android_release_readiness" hasf "android_release_readiness.sh" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs billing-collection:go-no-go" hasf "billing-collection:go-no-go" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs subscription-renewal:readiness" hasf "subscription-renewal:readiness" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs subscription-renewal:go-no-go" hasf "subscription-renewal:go-no-go" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs assembleDebug" hasf "assembleDebug" .github/workflows/sprint24-ci.yml
check "sprint24-ci runs testDebugUnitTest" hasf "testDebugUnitTest" .github/workflows/sprint24-ci.yml

echo "== Security: no renewal/CRM/gateway UI in Android =="
check "no payment gateway key in Android source" bash -c \
  '! grep -R "MIDTRANS_SERVER_KEY\|XENDIT_SECRET_KEY\|DUITKU_API_KEY\|CRM_API_KEY\|WHATSAPP_TOKEN" android/app/src/main/java android/app/src/main/res'
check "no Android renewal/dunning/admin panel" bash -c \
  '! grep -R "RenewalActivity\|DunningActivity\|SubscriptionRenewalActivity\|AdminRenewalActivity" android/app/src/main/java android/app/src/main/res'

echo "== Forbidden files =="
check "no .env committed" bash -c '! git ls-files | grep -qE "(^|/)\.env$"'
check "no vendor/node_modules committed" bash -c '! git ls-files | grep -qE "(^|/)(vendor|node_modules)/"'
check "no apk/aab/build/.gradle committed" bash -c '! git ls-files | grep -qE "\.apk$|\.aab$|(^|/)app/build/|(^|/)\.gradle/"'
check "no sqlite db committed" bash -c '! git ls-files | grep -qE "\.sqlite$|database\.sqlite"'
check "no keystore committed" bash -c '! git ls-files | grep -qE "\.keystore$|\.jks$"'

echo "== Android package/SDK intact =="
check "Android package intact" hasf "com.aishtech.poslite" android/app/build.gradle.kts
check "Android minSdk 26 intact" hasf "minSdk = 26" android/app/build.gradle.kts
check "Android targetSdk 35 intact" hasf "targetSdk = 35" android/app/build.gradle.kts

echo ""
echo "Passed: $pass  Failed: $fail"
[ "$fail" -eq 0 ]
