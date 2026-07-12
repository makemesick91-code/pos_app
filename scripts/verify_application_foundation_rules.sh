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
