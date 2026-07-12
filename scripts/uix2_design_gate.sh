#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"; cd "$ROOT"
fail=0; pass(){ printf '  [PASS] %s\n' "$1"; }; bad(){ printf '  [FAIL] %s\n' "$1"; fail=1; }
HOME=backend/resources/views/public-website/home.blade.php
LAYOUT=backend/resources/views/public-website/layout.blade.php
echo "== UIX-2 premium public experience gate =="
for id in fitur produk cara-kerja solusi offline pembayaran harga faq interest; do
  grep -q "id=\"$id\"" "$HOME" && pass "section #$id" || bad "missing #$id"
done
for target in fitur cara-kerja solusi harga faq interest; do
  grep -q "href=\"/#$target\"\|href=\"#$target\"" "$LAYOUT" "$HOME" && pass "CTA #$target" || bad "dead CTA #$target"
done
grep -q 'aria-expanded="false"' "$LAYOUT" && pass "accessible mobile menu" || bad "mobile menu ARIA missing"
grep -q 'role="tablist"' "$HOME" && pass "accessible product tabs" || bad "product tabs missing"
grep -q '<details>' "$HOME" && pass "native FAQ disclosure" || bad "FAQ disclosure missing"
grep -q 'prefers-reduced-motion' "$LAYOUT" && pass "reduced motion" || bad "reduced motion missing"
grep -q 'application/ld+json' "$LAYOUT" && pass "structured metadata" || bad "structured metadata missing"
grep -q 'Dalam tahap pilot' "$HOME" && pass "honest pilot pricing" || bad "pilot pricing wording missing"
if grep -RqnE 'Lorem ipsum|href="#"|10,000|★★★★★|https ready|HTTPS ready' backend/resources/views/public-website; then bad "placeholder/fake/unsupported content"; else pass "authentic content"; fi
if grep -RqnE '/home/|operator IP|aish_pos_pilot' docs/uiux/uix-2-* docs/deployment/uix-2-* 2>/dev/null; then bad "sensitive/local evidence"; else pass "evidence redacted"; fi
bash scripts/uix1_design_gate.sh
[ "$fail" -eq 0 ] || exit 1
echo "UIX-2 DESIGN GATE: PASS"
