#!/usr/bin/env bash
# Enforces the UIX-4 tenant-owner console governance rules (UIX4-R001..R022):
# owner views present + accessible, no fabricated/placeholder content, no
# credential leakage, read-only business surface, rules documented. Chains the
# UIX-3 (and thus UIX-2/UIX-1) gate.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }

V=backend/resources/views/owner
LOGIN=$V/login.blade.php
LAYOUT=$V/layout.blade.php
DASH=$V/dashboard.blade.php
RESTRICTED=$V/restricted.blade.php
DOC=docs/foundation/uix-4-tenant-owner-web-console.md
RULES=docs/PROJECT_RULES.md

echo "== UIX-4 tenant-owner console gate =="

# 1. Required views exist.
for f in "$LOGIN" "$LAYOUT" "$DASH" "$RESTRICTED" \
         "$V/outlets/index.blade.php" "$V/outlets/show.blade.php" \
         "$V/devices/index.blade.php" "$V/devices/show.blade.php" \
         "$V/subscription.blade.php" "$V/usage.blade.php" "$V/operations.blade.php"; do
  [ -f "$f" ] && pass "view $f" || bad "missing view $f"
done

# 2. Login page: labelled fields, CSRF, generic + accessible.
grep -q '@csrf' "$LOGIN" && pass "login CSRF" || bad "login missing CSRF"
grep -q 'for="email"' "$LOGIN" && grep -q 'for="password"' "$LOGIN" && pass "login field labels" || bad "login labels missing"
grep -q 'aria-pressed' "$LOGIN" && pass "password show/hide a11y" || bad "password toggle a11y missing"
grep -q 'noindex' "$LOGIN" && pass "login noindex" || bad "login not noindex"

# 3. Console shell accessibility (UIX4-R017).
grep -q 'aria-expanded' "$LAYOUT" && pass "nav aria-expanded" || bad "nav aria-expanded missing"
grep -q 'aria-current="page"' "$LAYOUT" && pass "current nav state" || bad "current nav state missing"
grep -q 'class="skip"' "$LAYOUT" && pass "skip link" || bad "skip link missing"
grep -q 'prefers-reduced-motion' "$LAYOUT" && pass "reduced motion" || bad "reduced motion missing"
grep -q 'overflow-x' "$LAYOUT" && pass "no horizontal overflow guard" || bad "overflow guard missing"
grep -q 'noindex' "$LAYOUT" && pass "console noindex" || bad "console not noindex"

# 4. Design tokens reused, build-free (UIX4-R017): views inline aish-tokens.css.
grep -q "resource_path('css/aish-tokens.css')" "$LAYOUT" && grep -q "resource_path('css/aish-tokens.css')" "$LOGIN" \
  && pass "tokens inlined build-free" || bad "aish-tokens.css not inlined"

# 5. Truthful metrics (UIX4-R010): unavailable state, no dead links / placeholders.
grep -q 'Tidak tersedia' "$DASH" && pass "truthful unavailable state" || bad "unavailable state missing"
if grep -RqnE 'Lorem ipsum|href="#"|★★★★★|HTTPS ready|https ready' "$V"; then bad "placeholder/fake content in owner views"; else pass "authentic owner content"; fi

# 6. No credential / secret leakage in views (UIX4-R012/R016).
if grep -RqniE 'password\s*=>\s*.[A-Za-z0-9]|admin123|changeme|owner123|secret\s*=>|\$2y\$' "$V"; then bad "possible secret/default credential in owner views"; else pass "no secret/default credential in views"; fi
# Device token/fingerprint hashes must never be printed in owner views.
if grep -RqnE 'activation_token_hash|device_fingerprint_hash' "$V"; then bad "device secret hash referenced in owner views"; else pass "no device secret hash in views"; fi

# 7. Read-only business surface (UIX4-R011): only auth (login/logout) may be non-GET on /owner.
if grep -RqnE "Route::(post|put|patch|delete)\('/owner/(outlets|devices|subscription|usage|operations|context)" backend/routes/web.php; then bad "unexpected owner business mutation route"; else pass "read-only owner business routes"; fi

# 8. Rules documented (UIX4-R001..R022).
for i in $(seq -w 1 22); do
  grep -q "UIX4-R0$i" "$DOC"   || bad "UIX4-R0$i missing in $DOC"
  grep -q "UIX4-R0$i" "$RULES" || bad "UIX4-R0$i missing in $RULES"
done
[ "$fail" -eq 0 ] && pass "all UIX4-R001..R022 documented" || true

# 9. Chain the prior design gates (UIX-3 -> UIX-2 -> UIX-1). No success-by-skipping.
bash scripts/uix3_design_gate.sh

[ "$fail" -eq 0 ] || { echo "UIX-4 DESIGN GATE: FAIL"; exit 1; }
echo "UIX-4 DESIGN GATE: PASS"
