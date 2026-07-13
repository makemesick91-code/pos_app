#!/usr/bin/env bash
# Application-foundation governance gate.
#
# Verifies that the permanent Aish POS foundation rules are present in the
# repository (not only in chat/prompts), that the platform-admin surfaces are
# protected, that no production default credential or tracked secret exists, and
# that release evidence is real (no placeholder) at closure. Wired into CI.
set -uo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

echo "== Application foundation rules gate =="

# 1. Root project instructions + modular rules present.
[ -f CLAUDE.md ] && pass "root CLAUDE.md" || bad "root CLAUDE.md missing"
for f in 00-project-foundation 10-architecture-and-source-of-truth 20-multi-tenancy-and-authorization \
         30-authentication-session-and-security 40-uiux-accessibility-and-responsive \
         50-data-privacy-audit-and-redaction 60-testing-quality-and-performance \
         70-ci-runtime-control 80-deployment-backup-and-rollback 90-release-evidence-and-go-tag; do
  [ -f ".claude/rules/$f.md" ] && pass "rule $f" || bad "missing .claude/rules/$f.md"
done
[ -f docs/governance/application-foundation-rules.md ] && pass "governance doc" || bad "governance doc missing"

# 2. Required security content in the auth rule.
grep -qiE 'no production default' .claude/rules/30-authentication-session-and-security.md \
  && pass "no-default-credential rule documented" || bad "auth rule missing default-credential clause"

# 3. Platform-admin surfaces are gated by middleware/policy.
grep -q "platform.admin.web" backend/bootstrap/app.php && pass "web admin gate registered" || bad "platform.admin.web not registered"
grep -q "platform.admin.web" backend/routes/web.php && pass "web admin routes guarded" || bad "/admin routes not guarded"
grep -q "'platform.admin'" backend/bootstrap/app.php && pass "api admin gate registered" || bad "platform.admin (api) missing"

# 3b. Tenant Owner Web Console surface is gated (UIX4-R001..R007).
grep -q "tenant.owner.web" backend/bootstrap/app.php && pass "owner web gate registered" || bad "tenant.owner.web not registered"
grep -q "tenant.owner.web" backend/routes/web.php && pass "owner routes guarded" || bad "/owner routes not guarded"
grep -q "OwnerContextResolver" backend/app/Http/Controllers/Owner/OwnerController.php && pass "owner context resolver used" || bad "owner context resolver not used"

# 4. No default PLATFORM-ADMIN credentials (UIX3-R003). The platform-admin
# console identity (is_platform_admin) must never be seeded with a password: it
# is provisioned only via the secure hidden-prompt command. (A legacy dev-only
# tenant/SAAS_ADMIN seeder using "password" for local demo is out of scope and
# is never run by the production deploy, which migrates but does not seed.)
if git ls-files 'backend/database/seeders' | xargs grep -lniE "is_platform_admin" 2>/dev/null | grep -q .; then
  bad "a seeder assigns is_platform_admin — platform admins must use secure provisioning, not a seeded default"
else
  pass "no seeded platform-admin identity"
fi
# No obviously-weak default credential ASSIGNED in app/config/routes (non-seeder).
# Matches an assignment to a weak literal (`=> 'admin123'`), not a mere mention —
# so the provisioning command's own denylist of forbidden values is not flagged
# (it is the control that REJECTS these). That file is also excluded explicitly.
if git ls-files 'backend/app' 'backend/config' 'backend/routes' \
   | grep -v 'PlatformAdminProvisionCommand.php' \
   | grep -v 'TenantOwnerProvisionCommand.php' \
   | xargs grep -lniE "=>[[:space:]]*['\"](admin123|changeme[0-9]*|password123)['\"]" 2>/dev/null | grep -q .; then
  bad "possible hardcoded default credential assigned in app/config/routes"
else
  pass "no hardcoded default credential assigned in app/config/routes"
fi

# 5. Provisioning command never accepts a visible password argument.
PROV=backend/app/Console/Commands/PlatformAdminProvisionCommand.php
if [ -f "$PROV" ]; then
  if grep -qE "\{--password" "$PROV"; then bad "provisioning exposes --password argument"; else pass "provisioning has no visible password arg"; fi
  grep -q "secret(" "$PROV" && pass "provisioning uses hidden prompt" || bad "provisioning missing hidden prompt"
else
  bad "provisioning command missing"
fi

# 5b. Owner provisioning command never accepts a visible password argument (UIX4-R012).
OPROV=backend/app/Console/Commands/TenantOwnerProvisionCommand.php
if [ -f "$OPROV" ]; then
  if grep -qE "\{--password" "$OPROV"; then bad "owner provisioning exposes --password argument"; else pass "owner provisioning has no visible password arg"; fi
  grep -q "secret(" "$OPROV" && pass "owner provisioning uses hidden prompt" || bad "owner provisioning missing hidden prompt"
else
  bad "owner provisioning command missing"
fi

# 5c. UIX-4 tenant-owner foundation is persisted in the repo.
for d in .claude/rules/25-tenant-owner-web-console-boundary.md \
         docs/foundation/uix-4-tenant-owner-web-console.md \
         docs/governance/tenant-owner-web-console-foundation.md; do
  [ -f "$d" ] && pass "uix-4 doc $(basename "$d")" || bad "missing uix-4 doc $d"
done

# 5d. UIX-5 subscription/billing/invoice console foundation (UIX5-R001..R028).
for d in .claude/rules/35-subscription-billing-invoice-integrity.md \
         docs/foundation/uix-5-subscription-billing-invoice-console.md \
         docs/governance/subscription-billing-invoice-foundation.md; do
  [ -f "$d" ] && pass "uix-5 doc $(basename "$d")" || bad "missing uix-5 doc $d"
done
# Every UIX5 rule id is persisted in the modular rule, the foundation doc, and PROJECT_RULES.
missing_ids=""
for i in $(seq -w 1 28); do
  id="UIX5-R0$i"
  if grep -q "$id" .claude/rules/35-subscription-billing-invoice-integrity.md \
     && grep -q "$id" docs/foundation/uix-5-subscription-billing-invoice-console.md \
     && grep -q "$id" docs/PROJECT_RULES.md; then :; else missing_ids="$missing_ids $id"; fi
done
[ -z "$missing_ids" ] && pass "UIX5-R001..R028 fully persisted" || bad "UIX-5 rule ids not fully persisted:$missing_ids"
# Owner + admin billing surfaces are wired (guarded by the checks in 3/3b above).
grep -q "OwnerBillingController" backend/routes/web.php && pass "owner billing routes present" || bad "owner billing routes missing"
grep -q "AdminBillingController" backend/routes/web.php && pass "admin billing routes present" || bad "admin billing routes missing"
# Billing read adapter is tenant-scoped and present.
SVC=backend/app/Services/BillingConsole/BillingConsoleReadService.php
if [ -f "$SVC" ] && grep -q "where('tenant_id'" "$SVC"; then
  pass "billing read adapter tenant-scoped"
else
  bad "BillingConsoleReadService missing or not tenant-scoped"
fi
# No unsafe float/cents money handling in billing console code (UIX5-R008).
if git ls-files 'backend/app/Services/BillingConsole' \
     'backend/app/Http/Controllers/Owner/OwnerBillingController.php' \
     'backend/app/Http/Controllers/Admin/AdminBillingController.php' \
   | xargs grep -lnE '\(float\)|floatval|/[[:space:]]*100([^0-9]|$)' 2>/dev/null | grep -q .; then
  bad "unsafe float/cents money handling in billing console code"
else
  pass "no unsafe float money in billing console code"
fi
# Console controllers are wired ONLY in the guarded web routes (no public/API exposure).
if git ls-files 'backend/routes' | grep -v 'routes/web.php' \
   | xargs grep -lnE 'OwnerBillingController|AdminBillingController' 2>/dev/null | grep -q .; then
  bad "billing console controller wired outside guarded web routes"
else
  pass "billing console controllers only in guarded web routes"
fi
# UI billing controllers perform no direct model mutation (UIX5-R015/R016).
if grep -nE '->(save|update|delete|forceDelete|insert)\(' \
     backend/app/Http/Controllers/Owner/OwnerBillingController.php \
     backend/app/Http/Controllers/Admin/AdminBillingController.php 2>/dev/null | grep -q .; then
  bad "billing UI controller performs a direct model mutation"
else
  pass "billing UI controllers are read-only"
fi
# Money is centrally formatted; billing views contain no inline number_format (UIX5-R010).
if git ls-files 'backend/resources/views/owner/billing' 'backend/resources/views/admin/billing' \
     'backend/resources/views/billing' \
   | grep -v 'components/rupiah.blade.php' \
   | xargs grep -lnE 'number_format' 2>/dev/null | grep -q .; then
  bad "billing view formats money inline instead of using <x-rupiah>"
else
  pass "billing views use the central rupiah component"
fi

# 5e. UIX-6 support/observability/incident console foundation (UIX6-R001..R033).
for d in .claude/rules/45-support-observability-incident-governance.md \
         docs/foundation/uix-6-support-observability-incident-console.md \
         docs/governance/support-observability-incident-foundation.md; do
  [ -f "$d" ] && pass "uix-6 doc $(basename "$d")" || bad "missing uix-6 doc $d"
done
# Every UIX6 rule id is persisted in the modular rule, the foundation doc, and PROJECT_RULES.
missing_uix6=""
for i in $(seq -w 1 33); do
  id="UIX6-R0$i"
  if grep -q "$id" .claude/rules/45-support-observability-incident-governance.md \
     && grep -q "$id" docs/foundation/uix-6-support-observability-incident-console.md \
     && grep -q "$id" docs/PROJECT_RULES.md; then :; else missing_uix6="$missing_uix6 $id"; fi
done
[ -z "$missing_uix6" ] && pass "UIX6-R001..R033 fully persisted" || bad "UIX-6 rule ids not fully persisted:$missing_uix6"
# Support/observability/incident console surfaces are wired in the guarded web routes (UIX6-R003).
grep -q "AdminSupportController" backend/routes/web.php && pass "admin support routes present" || bad "admin support routes missing"
grep -q "AdminObservabilityWebController" backend/routes/web.php && pass "admin observability route present" || bad "admin observability route missing"
grep -q "AdminIncidentController" backend/routes/web.php && pass "admin incident routes present" || bad "admin incident routes missing"
grep -q "OwnerSupportController" backend/routes/web.php && pass "owner support routes present" || bad "owner support routes missing"
# Read adapters exist and reuse canonical services (UIX6-R001/R002).
for svc in ObservabilityConsoleReadService SupportConsoleReadService IncidentConsoleReadService OwnerSupportReadService; do
  [ -f "backend/app/Services/SupportConsole/$svc.php" ] && pass "read adapter $svc" || bad "missing read adapter $svc"
done
# Console controllers are wired ONLY in the guarded web routes (no public/API exposure).
if git ls-files 'backend/routes' | grep -v 'routes/web.php' \
   | xargs grep -lnE 'AdminSupportController|AdminObservabilityWebController|AdminIncidentController|OwnerSupportController' 2>/dev/null | grep -q .; then
  bad "support/observability/incident console controller wired outside guarded web routes"
else
  pass "support console controllers only in guarded web routes"
fi
# UIX-6 UI controllers perform no direct model mutation (UIX6-R015/R016).
if grep -nE '->(save|update|delete|forceDelete|insert)\(' \
     backend/app/Http/Controllers/Admin/AdminSupportController.php \
     backend/app/Http/Controllers/Admin/AdminObservabilityWebController.php \
     backend/app/Http/Controllers/Admin/AdminIncidentController.php \
     backend/app/Http/Controllers/Owner/OwnerSupportController.php 2>/dev/null | grep -q .; then
  bad "a UIX-6 UI controller performs a direct model mutation"
else
  pass "UIX-6 UI controllers are read-only"
fi
# No raw stack trace / raw log payload rendering in the UIX-6 views (UIX6-R009).
if git ls-files 'backend/resources/views/admin/support' 'backend/resources/views/admin/observability' \
     'backend/resources/views/admin/incidents' 'backend/resources/views/owner/support' \
     'backend/resources/views/support' \
   | xargs grep -lnE 'getTraceAsString|\{!![[:space:]]*\$|storage_path\(.logs|file_get_contents\(.*logs' 2>/dev/null | grep -q .; then
  bad "a UIX-6 view renders raw trace/log/unescaped dynamic content"
else
  pass "UIX-6 views render no raw trace/log/unescaped content"
fi

# 6. No tracked secret files / keys.
if git ls-files | grep -qE '(^|/)\.env$|\.pem$|id_rsa|id_ed25519|_ed25519$|\.p12$|\.keystore$'; then
  bad "tracked secret/key file"
else
  pass "no tracked secret/key file"
fi

# 7. Release runbook + rollback docs present (evidence checked at closure).
for d in docs/deployment/uix-3-deployment-runbook.md docs/deployment/uix-3-rollback.md docs/deployment/uix-3-deployment-evidence.md; do
  [ -f "$d" ] && pass "release doc $(basename "$d")" || bad "missing release doc $d"
done

# 8. Release-closure mode: evidence must not contain placeholders.
if [ "${FOUNDATION_GATE_MODE:-}" = "closure" ]; then
  if grep -RqnE '<PLACEHOLDER>|TBD|TODO|FILL_ME|xxxxxxx' docs/deployment/uix-3-deployment-evidence.md; then
    bad "deployment evidence still has placeholders at closure"
  else
    pass "deployment evidence has no placeholders"
  fi
fi

[ "$fail" -eq 0 ] || { echo "APPLICATION FOUNDATION GATE: FAIL"; exit 1; }
echo "APPLICATION FOUNDATION GATE: PASS"
