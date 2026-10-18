# Circuit theme implementation plan

**Goal:** the `ecomodular/circuit` theme from the spec, editable in the customizer, desktop and phone.
**Spec:** `docs/superpowers/specs/2026-10-18-circuit-theme-design.md` (approved 2026-10-18).
**Execution:** native (in-session), screenshots compared with the reference after each page.

## Global constraints

- Theme files live in `themes/ecomodular/circuit/`; class prefix `ci-`; all CSS scoped under
  `[data-theme="ecomodular/circuit"]`.
- No files from the reference store. Photos: Unsplash licence (non-premium) or Pexels licence, listed with source,
  photographer and size, downloaded only after the merchant approves the list; credits in `assets/CREDITS.md`.
- Font: OFL, bundled woff2 + licence file.
- Real data only (no invented "sold" / "viewing" counters).
- Settings: every field has a `tab`; texts and links have sensible defaults matching the reference.
- Platform features are used, not re-implemented: page settings hook, `PlaceSlot` places, `SearchToggle`,
  `BuyButton`, `StickyAddToCart`, `DescriptionPanels`, `StockNote`, `VariantSelector`, platform cart page.

## Tasks

1. **Scaffold + test.** `theme.json` (design tokens, frontend map, home sections), `settings.json`, `README.md`,
   `LICENSE`, `src/components.tsx` (`useCircuit`, `CircuitCard`, `ProductRow`), `src/style.css` tokens.
   `tests/Feature/Themes/CircuitThemeTest.php`: manifest valid; every customizer field on a known tab; activation
   renders home/catalog/product/cart with Circuit.
2. **Header + footer** (desktop and phone), category rail, recently viewed panel (device storage), back-to-top.
3. **Homepage** sections 1–11 with countdown (date setting; hidden after it ends), brand strip from store brands.
4. **Catalog** with platform filters, grid density switch, promo card, round pagination.
5. **Product page** with thumbnails, delivery box, stock bar (real stock), accordions, marquee, related, recently
   viewed, all places.
6. **Cart** layout around the platform cart; empty state.
7. **Assets + demo:** approved photos, font, `demo.json` (categories + products), screenshot, credits.
8. **Visual pass:** 1280px and 390px screenshots against the reference; fix differences; full suites, types,
   lint, build.

Each task ends with: types check, lint of the theme files, `CircuitThemeTest`, a build and a screenshot check.
