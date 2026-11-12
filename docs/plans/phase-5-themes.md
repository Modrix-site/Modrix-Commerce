# Phase 5 Plan: Theme Engine

Branch: `feature/phase-5-themes` (based on `feature/phase-4-modules`). Each slice ships with tests and docs and is committed once the quality gates pass.

## Design summary

- **Themes are data, not code (ADR-031).** A theme is `themes/<vendor>/<name>/theme.json` plus images and fonts. It contains no PHP and no JavaScript, so a theme from the marketplace cannot run code on the store or in shoppers' browsers. Phase 0 sketched compiled React bundles; that would give themes the same trust problem as modules, so it is replaced by a controlled rendering model:
    - **Design tokens:** colors, radius, fonts, density and container width. The core turns them into CSS variables from strictly validated values.
    - **Layout variants:** header, footer, product card, grid columns and product page. Rendered by core components.
    - **Page sections:** the home page is an ordered list of typed sections (hero, featured products, category grid, image with text, rich text, promo banner). Each section type has a core-defined settings schema and a core component.
    - **Assets:** PNG, JPEG, WebP and WOFF2 only, served by a controlled route with `nosniff` and a restrictive CSP. No SVG, which can carry scripts.
- **Defaults vs merchant overrides.** The manifest holds the theme's defaults. Merchant changes are stored separately under `theme.<vendor>.<name>.*`: design and layout settings, logo, favicon, announcement and home sections. The theme that is rendered is a snapshot of the manifest applied by the merchant (`extensions.applied_manifest`). New files on disk become an update the merchant applies explicitly, after seeing the changelog. Overrides are never overwritten; overrides that no longer apply are reported, not deleted.
- **States** reuse the `extensions` table (`type = theme`): available (`discovered`), `invalid`, active (`enabled`, at most one), and `failed` (files missing). If the active theme is unusable, the storefront falls back to the bundled default theme, and then to built-in tokens.
- **Preview:** staff with `themes.manage` can preview any valid theme, with their customizations, in their own session. Shoppers keep seeing the active theme. A banner offers Activate or Exit.
- **Commerce stays in the core.** Cart, checkout, account and payment pages use core components and only pick up tokens. Themes cannot change behaviour, prices or security.
- **Demo content:** a theme may ship `demo.json` with categories and products. Importing it only adds items whose slugs and SKUs do not exist yet, never changes existing content, and requires explicit confirmation.

## Slices

| #   | Slice                        | Key areas                                                                                                                                                                                              |
| --- | ---------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 5.1 | Manifest, registry, resolver | `theme.json` validator (paths, colors, fonts, variants, sections, links, assets), discovery into `extensions`, applied snapshot, active/preview resolution with fallbacks, CSS generation, asset route |
| 5.2 | Storefront rendering         | Token CSS, layout variants (header, footer, card, grid, product page), home sections, announcement, logo and favicon, preview banner; default theme package matching and polishing the current look    |
| 5.3 | Theme manager                | Admin → Appearance: list, preview, activate, customize (design, layout, branding, home sections), reset, apply update with changelog, demo import; audit and lifecycle log                             |
| 5.4 | Sample themes                | Two premium-ready sample themes with distinct tokens, variants, sections, images and demo content                                                                                                      |
| 5.5 | Compatibility tests and docs | Every bundled theme runs browse → cart → checkout; developer guide, ADRs, roadmap                                                                                                                      |

## Acceptance criteria

- Switching themes never breaks home, listing, search, product, cart, checkout or account pages (tested per bundled theme).
- Invalid packages and paths are rejected: traversal, absolute paths, disallowed file types, script links, unknown section types, invalid colors and fonts (tested).
- Merchant overrides survive a theme update and switching themes back and forth (tested). Resetting them is an explicit action.
- Preview is visible only to the previewing staff member (tested).
