# UIX-2 Current-State Audit

Baseline `dbe2d38` was functional but visually resembled an internal template: a 960 px text-only layout, weak brand lockup, uniform cards, no product preview, limited navigation, basic packages, and no conversion narrative. UIX-1 tokens, seven implemented Android interfaces, public routes, a consented/rate-limited lead form, and Blade-only delivery were valid foundations and were retained.

Graph before: `/` → `public-website.home` → `public-website.layout`; `/packages`, `/privacy`, `/terms`, and `/thank-you` share the layout. CTA graph was `/` → `#interest` or `/packages`; form → `POST /interest` → `/thank-you`. No orphan public views were found. The approved logo asset was absent, so UIX-2 uses a text lockup plus neutral A monogram.

Brand decision: Aish Tech Solution is the company and Aish POS the customer-facing product family. `poslite` remains an internal Android package lineage; “Aish POS Lite” is removed from public copy.
