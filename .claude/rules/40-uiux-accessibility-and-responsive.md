# 40 — UI/UX, Accessibility & Responsive

Visual and interaction rules for the Blade public site and the `/admin/*` console.

## Design tokens (no hardcoded values)
- All color, spacing, and typography come from `backend/resources/css/aish-tokens.css`.
- Zero hardcoded hex colors in templates/CSS. White/blue is the foundation; gold is a
  limited accent only, never a primary surface or large fill.
- Build-free: no Node/Vite at deploy. Ship plain CSS/Blade that runs as-is on the VPS.

## Truthful UI
- The UI must reflect real service state. Never display success, "paid", "active", or
  fabricated metrics that the canonical services do not confirm. No placeholder data in a
  surface that looks authoritative.
- Admin console reads come from the same services as the API; the screen cannot claim more
  than the service returns.

## Accessibility
- Semantic HTML, labelled form controls, and visible focus states on interactive elements.
- Text/background pairings must meet WCAG AA contrast using token colors.
- Interactive controls are keyboard-operable; do not trap focus or rely on hover alone.

## Responsive
- Layouts use relative units and flex/grid; content must not overflow the viewport
  horizontally. Wide tables/diagrams scroll inside their own container.
- Verified across the standard width set used by the UIX visual checks.

## Gates
- Public site: `scripts/uix1_design_gate.sh`, `scripts/uix2_design_gate.sh`.
- Admin console (UIX-3): `scripts/uix3_design_gate.sh`. Rule set UIX3-R001..R016 is
  documented in `docs/foundation/uix-3-platform-admin-control-center.md` and
  `docs/PROJECT_RULES.md`. A design-gate failure blocks merge.
